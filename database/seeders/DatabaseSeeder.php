<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create demo user for testing
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@novamine.ai',
            'password' => 'demo1234', // Will be hashed by the model
        ]);
    }
}
