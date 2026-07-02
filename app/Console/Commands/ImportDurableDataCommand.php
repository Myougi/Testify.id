<?php

namespace App\Console\Commands;

use App\Support\DataMigration\DurableTableMap;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('qa:import-durable-data
    {path : Absolute or relative directory containing exported CSV files}
    {--connection= : Target database connection name}
    {--truncate : Delete existing durable rows before importing}
    {--issue-attachment-disk= : Rewrite issue_attachments.disk during import}')]
#[Description('Import durable application tables from CSV files into the current schema.')]
class ImportDurableDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = $this->resolveDirectory((string) $this->argument('path'));
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = DB::connection($connectionName);
        $attachmentDisk = $this->nullableString($this->option('issue-attachment-disk'));

        if (! File::isDirectory($directory)) {
            $this->components->error("Import directory [{$directory}] does not exist.");

            return self::FAILURE;
        }

        if ((bool) $this->option('truncate')) {
            $this->truncateDurableTables($connection);
        }

        foreach (DurableTableMap::definitions() as $index => $definition) {
            $table = $definition['table'];
            $filePath = $directory.DIRECTORY_SEPARATOR.sprintf('%02d_%s.csv', $index + 1, $table);

            if (! File::exists($filePath)) {
                $this->components->warn("Skipping {$table}; export file not found.");

                continue;
            }

            $imported = $this->importTable($connection, $definition, $filePath, $attachmentDisk);
            $this->components->info("Imported {$imported} row(s) into {$table}.");
        }

        $this->resetSequences($connection);
        $this->components->info("Durable data import completed from {$directory}.");

        return self::SUCCESS;
    }

    private function truncateDurableTables(ConnectionInterface $connection): void
    {
        foreach (array_reverse(DurableTableMap::tableNames()) as $table) {
            $connection->table($table)->delete();
        }
    }

    /**
     * @param  array{table: string, primary_key: string, sequence_column: string|null}  $definition
     */
    private function importTable(
        ConnectionInterface $connection,
        array $definition,
        string $filePath,
        ?string $attachmentDisk,
    ): int {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open import file [{$filePath}].");
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header) || $header === []) {
                return 0;
            }

            $rows = [];
            $count = 0;

            while (($csvRow = fgetcsv($handle)) !== false) {
                $record = [];

                foreach ($header as $offset => $column) {
                    $record[$column] = json_decode($csvRow[$offset] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                }

                if ($definition['table'] === 'issue_attachments' && $attachmentDisk !== null) {
                    $record['disk'] = $attachmentDisk;
                }

                $rows[] = $record;
                $count++;

                if (count($rows) >= 250) {
                    $this->upsertChunk($connection, $definition['table'], $definition['primary_key'], $rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                $this->upsertChunk($connection, $definition['table'], $definition['primary_key'], $rows);
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsertChunk(ConnectionInterface $connection, string $table, string $primaryKey, array $rows): void
    {
        $updateColumns = array_values(array_diff(array_keys($rows[0]), [$primaryKey]));

        $connection->table($table)->upsert($rows, $primaryKey, $updateColumns);
    }

    private function resetSequences(ConnectionInterface $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (DurableTableMap::definitions() as $definition) {
            $sequenceColumn = $definition['sequence_column'];

            if ($sequenceColumn === null) {
                continue;
            }

            $sequence = $connection->selectOne(
                'select pg_get_serial_sequence(?, ?) as sequence_name',
                [$definition['table'], $sequenceColumn],
            );

            /** @var string|null $sequenceName */
            $sequenceName = $sequence?->sequence_name ?? null;

            if ($sequenceName === null) {
                continue;
            }

            $maxValue = $connection->table($definition['table'])->max($sequenceColumn);

            if ($maxValue === null) {
                continue;
            }

            $connection->selectOne(
                'select setval(?::regclass, ?, true) as current_value',
                [$sequenceName, (int) $maxValue],
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function resolveDirectory(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
