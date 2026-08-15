<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Address;
use App\Models\Wallet;
use App\Models\LoyaltyPoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👥 Creating sample customers...');
        $customerPwd = Hash::make('Demo@123');

        $customer1 = User::updateOrCreate(
            ['email' => 'customer@threvolt.com'],
            [
                'first_name' => 'Demo', 'last_name' => 'Customer',
                'password' => $customerPwd,
                'phone_number' => '+91 98765 43210', 'role' => 'CUSTOMER',
                'is_email_verified' => true, 'is_active' => true,
            ]
        );
        $this->command->info('   ✓ customer@threvolt.com / Demo@123');

        $users = [$customer1];
        $extraUsers = [
            ['first_name' => 'Rahul', 'last_name' => 'Sharma', 'email' => 'rahul.sharma@email.com', 'phone' => '+91 98765 12345'],
            ['first_name' => 'Priya', 'last_name' => 'Patel', 'email' => 'priya.patel@email.com', 'phone' => '+91 99887 76655'],
            ['first_name' => 'Amit', 'last_name' => 'Kumar', 'email' => 'amit.kumar@email.com', 'phone' => '+91 91234 56789'],
        ];

        foreach ($extraUsers as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'first_name' => $u['first_name'], 'last_name' => $u['last_name'],
                    'password' => $customerPwd,
                    'phone_number' => $u['phone'], 'role' => 'CUSTOMER',
                    'is_email_verified' => true, 'is_active' => true,
                ]
            );
            $users[] = $user;
        }

        Address::create(['user_id' => $customer1->id, 'type' => 'HOME', 'first_name' => 'Demo', 'last_name' => 'Customer',
            'phone_number' => '+91 98765 43210', 'address_line1' => '123, MG Road', 'city' => 'Bangalore',
            'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[1]->id, 'type' => 'HOME', 'first_name' => 'Rahul', 'last_name' => 'Sharma',
            'phone_number' => '+91 98765 12345', 'address_line1' => '123, MG Road', 'address_line2' => 'Apartment 4B',
            'city' => 'Bangalore', 'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[2]->id, 'type' => 'HOME', 'first_name' => 'Priya', 'last_name' => 'Patel',
            'phone_number' => '+91 99887 76655', 'address_line1' => '78, Navrangpura',
            'city' => 'Ahmedabad', 'state' => 'Gujarat', 'zip_code' => '380009', 'country' => 'India', 'is_default' => true]);

        Address::create(['user_id' => $users[3]->id, 'type' => 'HOME', 'first_name' => 'Amit', 'last_name' => 'Kumar',
            'phone_number' => '+91 91234 56789', 'address_line1' => '45, Connaught Place',
            'city' => 'New Delhi', 'state' => 'Delhi', 'zip_code' => '110001', 'country' => 'India', 'is_default' => true]);

        $this->command->info('   ✓ Addresses created');

        // Wallets & Loyalty
        foreach ($users as $user) {
            Wallet::create(['user_id' => $user->id, 'balance' => rand(100, 2000), 'created_at' => now(), 'updated_at' => now()]);
            LoyaltyPoint::create(['user_id' => $user->id, 'points' => rand(50, 500), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->command->info('   ✓ Wallets & loyalty points created');
    }
}
