<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔔 Seeding notifications...');

        $storeName = 'THREVOLT';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}

        $users = User::where('role', 'CUSTOMER')->get();

        foreach ($users as $user) {
            DB::table('notifications')->insert([
                ['id' => Str::uuid(), 'user_id' => $user->id, 'type' => 'SYSTEM', 'title' => 'Welcome!', 'message' => "Welcome to {$storeName}! Start shopping now.", 'is_read' => false, 'created_at' => now(), 'updated_at' => now()],
                ['id' => Str::uuid(), 'user_id' => $user->id, 'type' => 'PROMOTION', 'title' => 'Summer Sale', 'message' => 'Get 30% off on all items! Use code SAVE30.', 'is_read' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        $this->command->info('   ✓ Notifications created');
    }
}
