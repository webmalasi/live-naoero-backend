<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin (Primary: admin@naoero-airlines.com)
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@naoero-airlines.com'],
            [
                'name' => 'Captain Nauru SuperAdmin',
                'password' => Hash::make('password'),
                'phone' => '+674 557 1000',
                'job_title' => 'Chief Executive & System Admin',
                'department' => 'Executive Office',
                'status' => 'active',
                'bio' => 'Lead administrator for Naoero Airlines executive and fleet operations control center.',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['Super Admin']);

        // 1b. Super Admin Alias (admin@naoeroairlines.com)
        $superAdmin2 = User::updateOrCreate(
            ['email' => 'admin@naoeroairlines.com'],
            [
                'name' => 'Captain Nauru SuperAdmin',
                'password' => Hash::make('password'),
                'phone' => '+674 557 1000',
                'job_title' => 'Chief Executive & System Admin',
                'department' => 'Executive Office',
                'status' => 'active',
                'bio' => 'Lead administrator for Naoero Airlines executive and fleet operations control center.',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin2->syncRoles(['Super Admin']);

        // 1c. Super Admin Alias (admin@admin.com)
        $superAdmin3 = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'phone' => '+674 557 1000',
                'job_title' => 'System Administrator',
                'department' => 'Executive Office',
                'status' => 'active',
                'bio' => 'Direct access administrator account.',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin3->syncRoles(['Super Admin']);

        // 2. Flight Operations Manager
        $opsManager = User::updateOrCreate(
            ['email' => 'ops@naoero-airlines.com'],
            [
                'name' => 'Alisa Stephen',
                'password' => Hash::make('password'),
                'phone' => '+674 557 2000',
                'job_title' => 'Director of Flight Operations',
                'department' => 'Flight Dispatch & Ground Services',
                'status' => 'active',
                'bio' => 'Overseeing Pacific island route network, Boeing 737 fleet schedule, and ground staff.',
                'email_verified_at' => now(),
            ]
        );
        $opsManager->syncRoles(['Flight Operations Manager']);

        // 3. Data Analyst
        $analyst = User::updateOrCreate(
            ['email' => 'analyst@naoero-airlines.com'],
            [
                'name' => 'Marcus Kun',
                'password' => Hash::make('password'),
                'phone' => '+674 557 3000',
                'job_title' => 'Senior Aviation Analyst',
                'department' => 'Revenue & Analytics',
                'status' => 'active',
                'bio' => 'Managing yield pricing, passenger load factor metrics, and fleet fuel telemetry.',
                'email_verified_at' => now(),
            ]
        );
        $analyst->syncRoles(['Data Analyst']);

        // 4. Ground Support Staff
        $staff = User::updateOrCreate(
            ['email' => 'staff@naoero-airlines.com'],
            [
                'name' => 'Kaitlin Dowabobo',
                'password' => Hash::make('password'),
                'phone' => '+674 557 4000',
                'job_title' => 'Customer Experience Supervisor',
                'department' => 'Passenger Terminal & Check-in',
                'status' => 'active',
                'bio' => 'Managing passenger ticketing, island transit assistance, and luggage dispatch.',
                'email_verified_at' => now(),
            ]
        );
        $staff->syncRoles(['Staff / Support']);

        // Log initial activity
        ActivityLog::create([
            'user_id' => $superAdmin->id,
            'action' => 'seed',
            'module' => 'System',
            'description' => 'System initialized with default roles, permissions, and executive airline administrative accounts.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Naoero Airlines Seeder Engine',
        ]);
    }
}
