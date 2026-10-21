<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Organizer;
use App\Models\Service;
use App\Models\ServiceSlot;
use App\Models\Setting;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('platform_commission_bps', (string) (int) config('ticketing.ticketing.commission_bps', 2000), 'payments', 'integer');
        Setting::put('currency', 'GHS', 'general', 'string');
        Setting::put('hold_minutes', (string) (int) config('ticketing.ticketing.hold_minutes', 10), 'general', 'integer');
        Setting::flush();

        $adminEmail = trim((string) config('ticketing.admin.email', 'admin@pulsetickets.test'));
        $adminPassword = (string) config('ticketing.admin.password', '');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Platform Admin',
                'phone' => '0240000001',
                'password' => Hash::make($adminPassword !== '' ? $adminPassword : 'password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $organizerUser = User::query()->updateOrCreate(
            ['email' => 'organizer@pulsetickets.test'],
            [
                'name' => 'Accra Nights Collective',
                'phone' => '0240000002',
                'password' => Hash::make('password'),
                'role' => 'organizer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $organizer = Organizer::query()->updateOrCreate(
            ['user_id' => $organizerUser->id],
            [
                'business_name' => 'Accra Nights Collective',
                'slug' => 'accra-nights-collective',
                'payout_momo_number' => '0240000002',
                'payout_provider' => 'mtn',
                'payout_name' => 'Accra Nights Collective',
                'status' => 'approved',
                'approved_at' => now(),
            ],
        );

        Wallet::query()->updateOrCreate(
            ['organizer_id' => $organizer->id],
            ['currency' => 'GHS', 'balance_minor' => 0, 'pending_minor' => 0, 'lifetime_earned_minor' => 0, 'lifetime_refunded_minor' => 0],
        );

        $event = Listing::query()->updateOrCreate(
            ['slug' => 'neon-nights-vol-3'],
            [
                'organizer_id' => $organizer->id,
                'type' => 'event',
                'title' => 'Neon Nights Vol. 3',
                'description' => 'The biggest underground club night of the year. Three rooms, six DJs, one very loud sound system.',
                'venue' => 'The Jam House',
                'city' => 'Accra',
                'region' => 'Greater Accra',
                'starts_at' => now()->addDays(12)->setTime(22, 0),
                'ends_at' => now()->addDays(13)->setTime(04, 0),
                'status' => 'published',
                'published_at' => now(),
                'allow_refunds' => true,
                'refund_window_hours' => 72,
                'max_per_order' => 6,
                'price_from_minor' => 6000,
            ],
        );

        $tiers = [
            ['name' => 'Early Bird', 'description' => 'Limited first release', 'price_minor' => 6000, 'capacity' => 40, 'sold_count' => 28, 'sort_order' => 0],
            ['name' => 'Regular', 'description' => 'Full night access', 'price_minor' => 10000, 'capacity' => 120, 'sold_count' => 54, 'sort_order' => 1],
            ['name' => 'VIP Table', 'description' => 'Table of 6 + bottle service', 'price_minor' => 45000, 'capacity' => 10, 'sold_count' => 9, 'sort_order' => 2],
        ];

        foreach ($tiers as $tier) {
            TicketType::query()->updateOrCreate(
                ['listing_id' => $event->id, 'name' => $tier['name']],
                [
                    'description' => $tier['description'],
                    'price_minor' => $tier['price_minor'],
                    'currency' => 'GHS',
                    'capacity' => $tier['capacity'],
                    'sold_count' => $tier['sold_count'],
                    'max_per_order' => 6,
                    'sort_order' => $tier['sort_order'],
                    'active' => true,
                ],
            );
        }

        $service = Listing::query()->updateOrCreate(
            ['slug' => 'studio-session-45min'],
            [
                'organizer_id' => $organizer->id,
                'type' => 'service',
                'title' => 'Studio Session — 45 min',
                'description' => 'Record a professional mix with our engineers. Includes mastering and two revisions.',
                'venue' => 'Rythm Lab Studio',
                'city' => 'Accra',
                'region' => 'Greater Accra',
                'starts_at' => now()->addDays(3)->setTime(9, 0),
                'status' => 'published',
                'published_at' => now(),
                'allow_refunds' => true,
                'refund_window_hours' => 48,
                'max_per_order' => 2,
                'price_from_minor' => 15000,
            ],
        );

        $serviceModel = Service::query()->updateOrCreate(
            ['listing_id' => $service->id, 'name' => 'Recording + mixing'],
            [
                'description' => 'One 45 minute session',
                'duration_minutes' => 45,
                'price_minor' => 15000,
                'capacity_per_slot' => 1,
            ],
        );

        foreach ([1, 2, 3] as $day) {
            ServiceSlot::query()->updateOrCreate(
                ['service_id' => $serviceModel->id, 'starts_at' => now()->addDays($day)->setTime(9 + $day, 0)],
                [
                    'ends_at' => now()->addDays($day)->setTime(10 + $day, 0),
                    'capacity' => 1,
                    'status' => 'open',
                ],
            );
        }

        $buyer = User::query()->updateOrCreate(
            ['email' => 'buyer@pulsetickets.test'],
            [
                'name' => 'Kojo Mensah',
                'phone' => '0240000003',
                'password' => Hash::make('password'),
                'role' => 'buyer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $admin->forceFill(['email_verified_at' => now()])->save();
        $organizerUser->forceFill(['email_verified_at' => now()])->save();
        $buyer->forceFill(['email_verified_at' => now()])->save();
    }
}
