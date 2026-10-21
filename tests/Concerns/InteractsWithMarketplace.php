<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Listing;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Builders for the marketplace fixtures used by the role based suites.
 *
 * Every amount is expressed in stored minor units, so UGX 50,000 becomes
 * 5,000,000 minor units the same way it does in production.
 */
trait InteractsWithMarketplace
{
    protected function createOrganizer(?User $user = null, array $attributes = []): Organizer
    {
        $user ??= User::factory()->create([
            'role' => 'organizer',
            'status' => 'active',
            'name' => 'DA-VELA Events',
            'phone' => '0765866555',
        ]);

        $organizer = Organizer::query()->create(array_merge([
            'user_id' => $user->id,
            'business_name' => 'DA-VELA',
            'slug' => 'da-vela-'.Str::lower(Str::random(6)),
            'payout_name' => 'DA-VELA',
            'payout_provider' => 'mtn',
            'payout_momo_number' => '0765866555',
            'payout_submitted_at' => now(),
            'payout_approved_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
        ], $attributes));

        Wallet::query()->create([
            'organizer_id' => $organizer->id,
            'currency' => Money::currency(),
            'balance_minor' => 0,
            'pending_minor' => 0,
            'lifetime_earned_minor' => 0,
            'lifetime_refunded_minor' => 0,
        ]);

        return $organizer;
    }

    protected function createListing(Organizer $organizer, array $attributes = []): Listing
    {
        return Listing::query()->create(array_merge([
            'organizer_id' => $organizer->id,
            'type' => 'event',
            'slug' => 'listing-'.Str::lower(Str::random(8)),
            'title' => 'Neon Symphony 2026',
            'description' => 'The biggest underground club night of the year.',
            'venue' => 'Lugogo Cricket Oval',
            'venue_address' => 'Plot 30, Lugogo Cricket Oval, Kampala',
            'city' => 'Kampala',
            'region' => 'Central Region',
            'latitude' => 0.3156,
            'longitude' => 32.5758,
            'starts_at' => now()->addDays(12)->setTime(22, 0),
            'status' => 'published',
            'published_at' => now(),
            'allow_refunds' => true,
            'refund_window_hours' => 72,
            'max_per_order' => 6,
            'price_from_minor' => Money::toMinor(30_000),
        ], $attributes));
    }

    protected function createTicketType(Listing $listing, array $attributes = []): TicketType
    {
        return TicketType::query()->create(array_merge([
            'listing_id' => $listing->id,
            'name' => 'Regular',
            'description' => 'Full night access',
            'price_minor' => Money::toMinor(50_000),
            'currency' => Money::currency(),
            'capacity' => 50,
            'max_per_order' => 6,
            'sort_order' => 0,
            'active' => true,
        ], $attributes));
    }

    protected function createBuyer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'buyer',
            'status' => 'active',
            'name' => 'Ivan Kaggwa',
            'phone' => '0771234567',
        ], $attributes));
    }

    /**
     * A settled order, used when a suite needs sales history without
     * replaying the whole Mobile Money checkout.
     */
    protected function createPaidOrder(Listing $listing, string $txRef): Order
    {
        return Order::query()->create([
            'tx_ref' => $txRef,
            'user_id' => $this->createBuyer()->id,
            'listing_id' => $listing->id,
            'type' => $listing->type,
            'status' => 'paid',
            'currency' => Money::currency(),
            'quantity' => 1,
            'subtotal_minor' => Money::toMinor(50_000),
            'commission_bps_applied' => 2000,
            'commission_minor' => Money::toMinor(10_000),
            'total_minor' => Money::toMinor(50_000),
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => 'ivan.kaggwa@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
            'gateway' => 'demo',
            'expires_at' => now()->addMinutes(10),
            'paid_at' => now(),
        ]);
    }
}
