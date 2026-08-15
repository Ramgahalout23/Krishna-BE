<?php

namespace Database\Seeders;

use App\Models\Subscriber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriberSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📧 Seeding subscribers...');
        Subscriber::insert([
            ['id' => Str::uuid(), 'email' => 'rahul.sharma@email.com', 'name' => 'Rahul Sharma', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'priya.patel@email.com', 'name' => 'Priya Patel', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'amit.kumar@email.com', 'name' => 'Amit Kumar', 'source' => 'SIGNUP', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'email' => 'sneha.gupta@example.com', 'name' => 'Sneha Gupta', 'source' => 'CHECKOUT', 'status' => 'SUBSCRIBED', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->command->info('   ✓ Subscribers created');
    }
}
