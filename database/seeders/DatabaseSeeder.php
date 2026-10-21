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
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('platform_commission_bps', (string) (int) config('ticketing.ticketing.commission_bps', 2000), 'payments', 'integer');
        Setting::put('currency', Money::currency(), 'general', 'string');
        Setting::put('hold_minutes', (string) (int) config('ticketing.ticketing.hold_minutes', 10), 'general', 'integer');
        Setting::flush();

        $adminEmail = trim((string) config('ticketing.admin.email', 'admin@novaratech.ug'));
        $adminPassword = (string) config('ticketing.admin.password', '');

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Novara Platform Admin',
                'phone' => '0770000001',
                'password' => Hash::make($adminPassword !== '' ? $adminPassword : 'password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $davelaUser = User::query()->updateOrCreate(
            ['email' => 'davela@novaratech.ug'],
            [
                'name' => 'DA-VELA Events',
                'phone' => '0765866555',
                'password' => Hash::make('password'),
                'role' => 'organizer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        // DA-VELA trades at a 0% platform commission, approved by the admin.
        $davela = Organizer::query()->updateOrCreate(
            ['user_id' => $davelaUser->id],
            [
                'business_name' => 'DA-VELA',
                'slug' => 'da-vela',
                'payout_momo_number' => '0765866555',
                'payout_provider' => 'mtn',
                'payout_name' => 'DA-VELA',
                'instagram' => config('ticketing.brand.instagram'),
                'whatsapp' => config('ticketing.brand.whatsapp'),
                'website' => config('ticketing.brand.website'),
                'commission_bps' => 0,
                'status' => 'approved',
                'approved_at' => now(),
                'payout_submitted_at' => now(),
                'payout_approved_at' => now(),
            ],
        );

        Wallet::query()->updateOrCreate(
            ['organizer_id' => $davela->id],
            [
                'currency' => Money::currency(),
                'balance_minor' => 0,
                'pending_minor' => 0,
                'lifetime_earned_minor' => 0,
                'lifetime_refunded_minor' => 0,
            ],
        );

        // A second organizer on the standard platform rate, still waiting for
        // the admin to approve its Mobile Money account.
        $crewUser = User::query()->updateOrCreate(
            ['email' => 'soundcrew@novaratech.ug'],
            [
                'name' => 'Kampala Sound Crew',
                'phone' => '0756123456',
                'password' => Hash::make('password'),
                'role' => 'organizer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $crew = Organizer::query()->updateOrCreate(
            ['user_id' => $crewUser->id],
            [
                'business_name' => 'Kampala Sound Crew',
                'slug' => 'kampala-sound-crew',
                'payout_momo_number' => '0756123456',
                'payout_provider' => 'airtel',
                'payout_name' => 'Kampala Sound Crew',
                'commission_bps' => null,
                'status' => 'pending',
                'payout_submitted_at' => now(),
            ],
        );

        Wallet::query()->updateOrCreate(
            ['organizer_id' => $crew->id],
            [
                'currency' => Money::currency(),
                'balance_minor' => 0,
                'pending_minor' => 0,
                'lifetime_earned_minor' => 0,
                'lifetime_refunded_minor' => 0,
            ],
        );

        $event = Listing::query()->updateOrCreate(
            ['slug' => 'neon-nights-vol-3'],
            [
                'organizer_id' => $davela->id,
                'type' => 'event',
                'title' => 'Neon Symphony 2026',
                'description' => 'The biggest underground club night of the year. Three rooms, six DJs, one very loud sound system — now live in Kampala.',
                'venue' => 'Lugogo Cricket Oval',
                'venue_address' => 'Plot 30, Lugogo Cricket Oval, Kampala',
                'city' => 'Kampala',
                'region' => 'Central Region',
                'latitude' => 0.3156,
                'longitude' => 32.5758,
                'starts_at' => now()->addDays(12)->setTime(22, 0),
                'ends_at' => now()->addDays(13)->setTime(04, 0),
                'status' => 'published',
                'published_at' => now(),
                'allow_refunds' => true,
                'refund_window_hours' => 72,
                'max_per_order' => 6,
                'price_from_minor' => Money::toMinor(30_000),
            ],
        );

        $tiers = [
            ['name' => 'Early Bird', 'description' => 'Limited first release', 'price' => 30_000, 'capacity' => 40, 'sold_count' => 28, 'sort_order' => 0],
            ['name' => 'Regular', 'description' => 'Full night access', 'price' => 50_000, 'capacity' => 120, 'sold_count' => 54, 'sort_order' => 1],
            ['name' => 'VIP Table', 'description' => 'Table of 6 + bottle service', 'price' => 250_000, 'capacity' => 10, 'sold_count' => 9, 'sort_order' => 2],
        ];

        foreach ($tiers as $tier) {
            TicketType::query()->updateOrCreate(
                ['listing_id' => $event->id, 'name' => $tier['name']],
                [
                    'description' => $tier['description'],
                    'price_minor' => Money::toMinor($tier['price']),
                    'currency' => Money::currency(),
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
                'organizer_id' => $davela->id,
                'type' => 'service',
                'title' => 'Studio Session — 45 min',
                'description' => 'Record a professional mix with our engineers. Includes mastering and two revisions.',
                'venue' => 'Bombo Recording Studios',
                'venue_address' => 'Bombo Road, Nakawa, Kampala',
                'city' => 'Kampala',
                'region' => 'Central Region',
                'latitude' => 0.3443,
                'longitude' => 32.6325,
                'starts_at' => now()->addDays(3)->setTime(9, 0),
                'status' => 'published',
                'published_at' => now(),
                'allow_refunds' => true,
                'refund_window_hours' => 48,
                'max_per_order' => 2,
                'price_from_minor' => Money::toMinor(75_000),
            ],
        );

        $serviceModel = Service::query()->updateOrCreate(
            ['listing_id' => $service->id, 'name' => 'Recording + mixing'],
            [
                'description' => 'One 45 minute session',
                'duration_minutes' => 45,
                'price_minor' => Money::toMinor(75_000),
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

        User::query()->updateOrCreate(
            ['email' => 'buyer@novaratech.ug'],
            [
                'name' => 'Ivan Kaggwa',
                'phone' => '0771234567',
                'password' => Hash::make('password'),
                'role' => 'buyer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
    }
}
