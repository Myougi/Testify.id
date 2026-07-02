<?php

namespace App\Console\Commands;

use App\Support\DataMigration\DurableTableMap;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('qa:export-durable-data
    {--connection= : Source database connection name}
    {--path= : Absolute or relative output directory}')]
#[Description('Export durable application tables to CSV files for cross-database migration.')]
class ExportDurableDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = DB::connection($connectionName);
        $directory = $this->resolveDirectory((string) ($this->option('path') ?: 'storage/app/data-migrations/latest'));

        File::ensureDirectoryExists($directory);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'connection' => $connectionName,
            'tables' => [],
        ];

        foreach (DurableTableMap::definitions() as $index => $definition) {
            $table = $definition['table'];
            $columns = Schema::connection($connectionName)->getColumnListing($table);
            $filePath = $directory.DIRECTORY_SEPARATOR.sprintf('%02d_%s.csv', $index + 1, $table);
            $count = $this->exportTable($connection, $table, $definition['primary_key'], $columns, $filePath);

            $manifest['tables'][] = [
                'table' => $table,
                'file' => basename($filePath),
                'count' => $count,
            ];

            $this->components->info("Exported {$count} row(s) from {$table}.");
        }

        File::put(
            $directory.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        $this->components->info("Durable data export completed at {$directory}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $columns
     */
    private function exportTable(
        ConnectionInterface $connection,
        string $table,
        string $primaryKey,
        array $columns,
        string $filePath,
    ): int {
        $handle = fopen($filePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open export file [{$filePath}].");
        }

        try {
            fputcsv($handle, $columns);

            $count = 0;
            $rows = $connection->table($table)
                ->orderBy($primaryKey)
                ->cursor();

            foreach ($rows as $row) {
                $payload = [];

                foreach ($columns as $column) {
                    /** @var mixed $value */
                    $value = $row->{$column};
                    $payload[] = json_encode($value, JSON_THROW_ON_ERROR);
                }

                fputcsv($handle, $payload);
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    private function resolveDirectory(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
