<?php

use App\Models\Issue;
use App\Models\IssueAttachment;
use App\Models\Project;
use App\Models\User;
use App\Support\DataMigration\DurableTableMap;
use Illuminate\Support\Facades\File;

test('durable data can be exported and re-imported with attachment disk remapping', function (): void {
    $exportPath = 'storage/framework/testing/durable-export';

    File::deleteDirectory(base_path($exportPath));

    $user = User::factory()->create([
        'name' => 'Supabase QA',
        'email' => 'supabase@example.com',
    ]);
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Cloud Cutover',
    ]);
    $issue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Imported issue evidence',
        'reporter' => 'Migration Bot',
    ]);
    $attachment = IssueAttachment::factory()->for($issue)->create([
        'disk' => 'local',
        'path' => 'issues/imported-proof.png',
        'original_name' => 'imported-proof.png',
    ]);

    $this->artisan('qa:export-durable-data', ['--path' => $exportPath])
        ->assertSuccessful();

    $exportedFiles = collect(DurableTableMap::definitions())
        ->mapWithKeys(fn (array $definition, int $index): array => [
            $definition['table'] => sprintf('%02d_%s.csv', $index + 1, $definition['table']),
        ]);

    expect(File::exists(base_path($exportPath.'/'.$exportedFiles['users'])))->toBeTrue();
    expect(File::exists(base_path($exportPath.'/'.$exportedFiles['projects'])))->toBeTrue();
    expect(File::exists(base_path($exportPath.'/'.$exportedFiles['issues'])))->toBeTrue();
    expect(File::exists(base_path($exportPath.'/'.$exportedFiles['issue_attachments'])))->toBeTrue();
    expect(File::exists(base_path($exportPath.'/manifest.json')))->toBeTrue();

    $manifest = json_decode(File::get(base_path($exportPath.'/manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    expect(collect($manifest['tables'])->firstWhere('table', 'issue_attachments')['count'])->toBe(1);

    $this->artisan('qa:import-durable-data', [
        'path' => $exportPath,
        '--truncate' => true,
        '--issue-attachment-disk' => 's3',
    ])->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'supabase@example.com',
    ]);

    $this->assertDatabaseHas('projects', [
        'project_id' => $project->project_id,
        'project_name' => 'Cloud Cutover',
    ]);

    $this->assertDatabaseHas('issues', [
        'id' => $issue->id,
        'title' => 'Imported issue evidence',
        'project_id' => $project->project_id,
    ]);

    $this->assertDatabaseHas('issue_attachments', [
        'id' => $attachment->id,
        'issue_id' => $issue->id,
        'disk' => 's3',
        'path' => 'issues/imported-proof.png',
    ]);
});
