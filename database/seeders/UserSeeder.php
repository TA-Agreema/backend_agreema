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
        $users = [
            [
                'email' => 'admin@agreema.com',
                'name' => 'Admin Agreema',
                'password' => 'password123',
                'job_title' => 'Administrator',
                'department' => 'IT',
                'role' => 'admin',
            ],
            [
                'email' => 'manager@agreema.com',
                'name' => 'Manager Agreema',
                'password' => 'password123',
                'job_title' => 'Project Manager',
                'department' => 'Management',
                'role' => 'manager',
            ],
            [
                'email' => 'hrd@agreema.com',
                'name' => 'HRD Agreema',
                'password' => 'password123',
                'job_title' => 'Software Developer',
                'department' => 'Engineering',
                'role' => 'hrd',
            ],
            [
                'email' => 'legal@agreema.com',
                'name' => 'Legal Agreema',
                'password' => 'password123',
                'job_title' => 'Client',
                'department' => null,
                'role' => 'legal',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt($userData['password']),
                    'job_title' => $userData['job_title'],
                    'department' => $userData['department'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole($userData['role']);
        }
    }
}
