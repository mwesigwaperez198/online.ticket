<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\Fixtures\RecordingMomoGateway;
use Tests\TestCase;

final class LastTicketConcurrencyTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_exactly_one_buyer_wins_the_last_ticket_when_100_checkout_requests_race(): void
    {
        // This suite proves the inventory guard, so the checkout throttle is
        // switched off — it is covered by the rate limiting suite instead.
        $this->withoutMiddleware(ThrottleRequests::class);

        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, [
            'capacity' => 1,
            'sold_count' => 0,
            'held_count' => 0,
            'price_minor' => Money::toMinor(30_000),
        ]);

        $buyers = collect();
        $orders = collect();
        $errors = collect();

        for ($i = 0; $i < 100; $i++) {
            $buyers->push($this->createBuyer([
                'email' => "racer{$i}@novaratech.ug",
                'name' => "Racer {$i}",
            ]));
        }

        DB::transaction(function () use ($buyers, $listing, $tier, &$orders, &$errors): void {
            $buyers->each(function (User $buyer) use ($listing, $tier, &$orders, &$errors): void {
                $gateway = new RecordingMomoGateway;
                app()->instance(MomoGateway::class, $gateway);

                $response = $this->actingAs($buyer)->post(route('checkout.store'), [
                    'listing_id' => $listing->id,
                    'ticket_type_id' => $tier->id,
                    'quantity' => 1,
                    'buyer_name' => $buyer->name,
                    'buyer_email' => $buyer->email,
                    'buyer_phone' => '0771234567',
                    'momo_provider' => 'mtn',
                ]);

                if ($response->isRedirect() && ! session()->has('error')) {
                    $order = Order::query()->where('user_id', $buyer->id)->first();

                    if ($order !== null) {
                        $orders->push($order->id);
                    }
                } else {
                    $errors->push(session('error'));
                }
            });
        });

        $this->assertSame(1, $orders->count(), 'Exactly one buyer may hold the last ticket.');
        $this->assertGreaterThanOrEqual(99, $errors->count());

        $tier->refresh();

        $this->assertSame(1, $tier->held_count + $tier->sold_count);
        $this->assertSame(1, (int) Order::query()->whereIn('id', $orders->all())->where('status', 'pending')->count());
        $this->assertSame(0, (int) Order::query()->whereIn('id', $orders->all())->where('status', 'paid')->count());

        $other = $this->createBuyer(['email' => 'late@novaratech.ug', 'name' => 'Late Buyer']);
        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $this->actingAs($other)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => $other->name,
            'buyer_email' => $other->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertSessionHas('error');

        $this->assertSame(1, Order::query()->count());
    }
}
