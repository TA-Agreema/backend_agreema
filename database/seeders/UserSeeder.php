<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => 'admin@agreema.com',
            'name' => 'Admin Agreema',
            'password' => bcrypt('password123'),
            'job_title' => 'Administrator',
            'department' => 'IT',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('admin');
    }
}
