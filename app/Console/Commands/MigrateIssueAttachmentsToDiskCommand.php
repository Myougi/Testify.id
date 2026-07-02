<?php

namespace App\Console\Commands;

use App\Models\IssueAttachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('qa:migrate-issue-attachments-to-disk
    {--connection= : Database connection name}
    {--from=local : Source filesystem disk}
    {--to=s3 : Target filesystem disk}
    {--only-missing : Skip files that already exist on the target disk}
    {--update-database : Rewrite issue_attachments.disk to the target disk after a successful copy}
    {--delete-source : Delete the source file after a successful copy}')]
#[Description('Copy existing issue attachment files to another filesystem disk while preserving paths.')]
class MigrateIssueAttachmentsToDiskCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connectionName = $this->nullableString($this->option('connection'));
        $fromDisk = (string) $this->option('from');
        $toDisk = (string) $this->option('to');
        $onlyMissing = (bool) $this->option('only-missing');
        $updateDatabase = (bool) $this->option('update-database');
        $deleteSource = (bool) $this->option('delete-source');

        if ($fromDisk === $toDisk) {
            $this->components->error('Source and target disks must be different.');

            return self::FAILURE;
        }

        $query = $connectionName === null
            ? IssueAttachment::query()->where('disk', $fromDisk)
            : IssueAttachment::on($connectionName)->where('disk', $fromDisk);

        $copied = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($query->orderBy('id')->cursor() as $attachment) {
            if (! Storage::disk($fromDisk)->exists($attachment->path)) {
                $missing++;
                $this->components->warn("Missing source file for attachment {$attachment->id}: {$attachment->path}");

                continue;
            }

            if ($onlyMissing && Storage::disk($toDisk)->exists($attachment->path)) {
                $skipped++;

                if ($updateDatabase) {
                    $attachment->forceFill(['disk' => $toDisk])->save();
                }

                continue;
            }

            $stream = Storage::disk($fromDisk)->readStream($attachment->path);

            if (! is_resource($stream)) {
                throw new \RuntimeException("Unable to read [{$attachment->path}] from disk [{$fromDisk}].");
            }

            try {
                $written = Storage::disk($toDisk)->writeStream($attachment->path, $stream);
            } finally {
                fclose($stream);
            }

            if ($written === false) {
                throw new \RuntimeException("Unable to write [{$attachment->path}] to disk [{$toDisk}].");
            }

            if ($updateDatabase) {
                $attachment->forceFill(['disk' => $toDisk])->save();
            }

            if ($deleteSource) {
                Storage::disk($fromDisk)->delete($attachment->path);
            }

            $copied++;
        }

        $this->components->info("Attachment migration completed. Copied: {$copied}, skipped: {$skipped}, missing: {$missing}.");

        return self::SUCCESS;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
