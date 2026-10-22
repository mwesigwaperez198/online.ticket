<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core Platform Application Configuration Settings
        Setting::put('platform_commission_bps', (string) (int) config('ticketing.ticketing.commission_bps', 2000), 'payments', 'integer');
        Setting::put('currency', Money::currency(), 'general', 'string');
        Setting::put('hold_minutes', (string) (int) config('ticketing.ticketing.hold_minutes', 10), 'general', 'integer');
        Setting::flush();

        // 2. Fetch Production Fallbacks for the Admin User
        $adminEmail = trim((string) config('ticketing.admin.email', 'admin@novaratech.ug'));
        $adminPassword = (string) config('ticketing.admin.password', '');

        // 3. Keep ONLY the Master Administrator Account (No Mocks/Organizers)
        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Novara Platform Admin',
                'phone' => '0770000001',
                // Explicitly hashes the fallback password string to resolve login rejections
                'password' => Hash::make($adminPassword !== '' ? $adminPassword : 'password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
    }
}
