<?php

use Database\Seeders\DatabaseSeeder;

test('database seeder creates the configured demo users and snapshot application records', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 6);
    $this->assertDatabaseCount('projects', 1);
    $this->assertDatabaseCount('test_repository_modules', 4);
    $this->assertDatabaseCount('test_plans', 3);
    $this->assertDatabaseCount('test_cases', 26);
    $this->assertDatabaseCount('test_case_steps', 71);
    $this->assertDatabaseCount('test_runs', 3);
    $this->assertDatabaseCount('issues', 1);
    $this->assertDatabaseCount('issue_attachments', 1);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@testify.id',
    ]);

    $this->assertDatabaseHas('projects', [
        'project_id' => 1,
        'project_name' => 'Spider Healthcare',
    ]);
});
