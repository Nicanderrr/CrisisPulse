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
        User::where('email', 'admin@crisispulse.test')->update(['email' => 'admin@gmail.com']);
        User::where('email', 'staff@crisispulse.test')->update(['email' => 'staff@gmail.com']);

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'System Administrator', 'role' => 'system_admin', 'password' => Hash::make('password')]
        );

        User::updateOrCreate(
            ['email' => 'staff@gmail.com'],
            ['name' => 'Crisis Desk Staff', 'role' => 'staff', 'password' => Hash::make('password')]
        );
    }
}
