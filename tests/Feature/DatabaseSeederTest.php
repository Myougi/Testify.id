<?php

use Database\Seeders\DatabaseSeeder;

test('database seeder creates the configured demo users without application records', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 6);
    $this->assertDatabaseCount('projects', 0);
    $this->assertDatabaseCount('test_plans', 0);
    $this->assertDatabaseCount('test_cases', 0);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@testify.id',
    ]);
});
