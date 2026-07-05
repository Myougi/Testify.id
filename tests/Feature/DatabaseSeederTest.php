<?php

use Database\Seeders\DatabaseSeeder;

test('database seeder creates the configured demo users', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 6);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@testify.id',
    ]);
});
