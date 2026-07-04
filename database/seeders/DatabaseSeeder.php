<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->createMany([
            [
                'name' => 'Admin Testify',
                'email' => 'admin@testify.id',
                'password' => Hash::make('#Janganbuka@2026'),
            ],
            [
                'name' => 'Demo Account 1',
                'email' => 'demo1@testify.id',
                'password' => Hash::make('Password123'),
            ],
            [
                'name' => 'Demo Account 2',
                'email' => 'demo2@testify.id',
                'password' => Hash::make('Password123'),
            ],
            [
                'name' => 'Demo Account 3',
                'email' => 'demo3@testify.id',
                'password' => Hash::make('Password123'),
            ],
            [
                'name' => 'Demo Account 4',
                'email' => 'demo4@testify.id',
                'password' => Hash::make('Password123'),
            ],
            [
                'name' => 'Demo Account 5',
                'email' => 'demo5@testify.id',
                'password' => Hash::make('Password123'),
            ],
        ]);

        // $this->call(ExistingDatabaseSnapshotSeeder::class);
    }
}
