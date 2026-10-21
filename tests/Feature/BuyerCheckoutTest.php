<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\Fixtures\RecordingMomoGateway;
use Tests\TestCase;

final class BuyerCheckoutTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_the_order_is_committed_before_the_mobile_money_gateway_is_called(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, [
            'name' => 'VIP Table',
            'price_minor' => Money::toMinor(250_000),
        ]);

        $buyer = $this->createBuyer();

        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $response = $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 2,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $response->assertRedirect($gateway->authorizationUrl);

        // The row was already committed when the gateway was contacted.
        $this->assertTrue($gateway->orderExistedAtInitialize);
        $this->assertSame('pending', $gateway->orderStatusAtInitialize);
        $this->assertSame(Money::toMinor(500_000), $gateway->orderTotalAtInitialize);

        $order = Order::query()->firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertSame('UGX', $order->currency);
        $this->assertSame(Money::toMinor(500_000), $order->total_minor);
        $this->assertSame(Money::toMinor(500_000), $order->subtotal_minor);
        $this->assertSame(2, (int) $tier->fresh()->held_count);
        $this->assertSame(0, (int) $tier->fresh()->sold_count);

        $this->assertMatchesRegularExpression('/^TK[A-Z0-9]{14}$/', $order->tx_ref);
    }

    public function test_every_checkout_uses_a_unique_transaction_reference(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($buyer)->post(route('checkout.store'), [
                'listing_id' => $listing->id,
                'ticket_type_id' => $tier->id,
                'quantity' => 1,
                'buyer_name' => 'Ivan Kaggwa',
                'buyer_email' => $buyer->email,
                'buyer_phone' => '0771234567',
                'momo_provider' => 'mtn',
            ])->assertRedirect();
        }

        $references = Order::query()->pluck('tx_ref')->all();

        $this->assertCount(3, $references);
        $this->assertCount(3, array_unique($references));
    }

    public function test_a_buyer_cannot_check_out_with_a_number_outside_uganda(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0240000003',
            'momo_provider' => 'mtn',
        ])->assertSessionHasErrors('buyer_phone');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, (int) $tier->fresh()->held_count);
    }

    public function test_an_unapproved_payment_leaves_the_order_pending(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('checkout.return', ['reference' => $order->tx_ref]))
            ->assertRedirect(route('buyer.orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_buyer_receives_the_ticket_after_the_mobile_money_prompt_is_approved(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 2,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $order = Order::query()->firstOrFail();

        $gateway->approve($order->tx_ref);

        $this->actingAs($buyer)
            ->get(route('checkout.return', ['reference' => $order->tx_ref]))
            ->assertRedirect(route('buyer.orders.show', $order));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame(2, (int) $tier->fresh()->sold_count);
        $this->assertSame(0, (int) $tier->fresh()->held_count);

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('issued', $ticket->status);
        $this->assertNotNull($ticket->qr_code_hash);
        $this->assertSame(64, strlen((string) $ticket->qr_code_hash));

        $this->actingAs($buyer)
            ->get(route('buyer.tickets'))
            ->assertOk()
            ->assertSee($ticket->code)
            ->assertSee('Neon Symphony 2026');
    }

    public function test_a_buyer_cannot_open_the_organizer_or_admin_areas(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)->get(route('organizer.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.approvals.index'))->assertForbidden();
    }
}
