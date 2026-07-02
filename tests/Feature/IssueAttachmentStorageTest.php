<?php

use App\Models\Issue;
use App\Models\IssueAttachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('issue uploads use the configured attachment disk', function (): void {
    config()->set('filesystems.issue_attachments_disk', 's3');
    Storage::fake('s3');

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $image = UploadedFile::fake()->image('supabase-cutover.png', 1200, 800)->size(2048);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->post(route('api.issues.store'), [
            'title' => 'Supabase storage check',
            'description' => 'Verify evidence uploads land on the configured disk.',
            'priority' => Issue::PRIORITY_HIGH,
            'severity' => Issue::SEVERITY_HIGH,
            'status' => Issue::STATUS_OPEN,
            'reporter' => 'Cloud QA',
            'image' => $image,
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.image.name', 'supabase-cutover.png')
        ->assertJsonPath('data.image.mime_type', 'image/png');

    $attachment = IssueAttachment::query()->sole();

    expect($attachment->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($attachment->path);
});

test('issue attachment migration command copies files to the target disk and updates the database', function (): void {
    Storage::fake('local');
    Storage::fake('s3');

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $issue = Issue::factory()->for($project, 'project')->create();
    Storage::disk('local')->put('issues/cutover/evidence.png', 'binary-image-data');
    $attachment = IssueAttachment::factory()->for($issue)->create([
        'disk' => 'local',
        'path' => 'issues/cutover/evidence.png',
        'original_name' => 'evidence.png',
    ]);

    $this->artisan('qa:migrate-issue-attachments-to-disk', [
        '--from' => 'local',
        '--to' => 's3',
        '--update-database' => true,
        '--delete-source' => true,
    ])->assertSuccessful();

    $attachment->refresh();

    expect($attachment->disk)->toBe('s3');
    Storage::disk('s3')->assertExists('issues/cutover/evidence.png');
    Storage::disk('local')->assertMissing('issues/cutover/evidence.png');
});
