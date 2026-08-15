<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminTestUserSeeder extends Seeder
{
    /**
     * Seed the admin test user and generate a Sanctum Bearer token.
     *
     * Creates or updates an admin user with known credentials and generates
     * a Sanctum API token so you can immediately test admin endpoints.
     *
     * Usage:
     *   php artisan db:seed --class=AdminTestUserSeeder
     *
     * Credentials:
     *   Email:    admin@threvolt.com
     *   Password: Admin@123
     */
    public function run(): void
    {
        $this->command->info('Seeding admin test user...');

        $admin = User::updateOrCreate(
            ['email' => 'admin@threvolt.com'],
            [
                'first_name'       => 'Admin',
                'last_name'        => 'THREVOLT',
                'password'         => Hash::make('Admin@123'),
                'phone_number'     => '+91 98765 43210',
                'role'             => 'ADMIN',
                'is_email_verified' => true,
                'is_active'        => true,
                'is_blocked'       => false,
            ]
        );

        // Revoke old tokens and generate a fresh one
        $admin->tokens()->delete();
        $token = $admin->createToken('admin-test-token')->plainTextToken;

        $this->command->info('');
        $this->command->info('Admin test user ready!');
        $this->command->info('----------------------------');
        $this->command->info('  Email:        admin@threvolt.com');
        $this->command->info('  Password:     Admin@123');
        $this->command->info('  Role:         ' . $admin->role);
        $this->command->info('  UUID:         ' . $admin->id);
        $this->command->info('');
        $this->command->info('  Bearer Token:');
        $this->command->info('  ' . $token);
        $this->command->info('');
        $this->command->info('Quick test (copy-paste ready):');
        $this->command->info('  curl -H "Authorization: Bearer ' . $token . '" -H "Accept: application/json" \\');
        $this->command->info('    "http://localhost:8000/api/v1/admin/analytics/products"');
        $this->command->info('');
    }
}
