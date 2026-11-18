<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isLocal()) {
            User::query()->firstOrCreate(
                ['email' => 'demo@example.com'],
                ['name' => 'Demo User', 'password' => 'issue-tracker-demo'],
            );
            User::query()->firstOrCreate(
                ['email' => 'demo2@example.com'],
                ['name' => 'Demo User 2', 'password' => 'issue-tracker-demo'],
            );

            $this->call(DemoIssueSeeder::class);
        }
    }
}
