<?php

use Database\Seeders\DatabaseSeeder;

test('database seeder creates the configured demo users and snapshot application records', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 6);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@testify.id',
    ]);
});
