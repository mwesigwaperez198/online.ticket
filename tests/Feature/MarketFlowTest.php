<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\Service;
use App\Models\ServiceSlot;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class MarketFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_novara_secret_key';

    private User $buyer;

    private Listing $listing;

    private TicketType $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);

        $organizer = Organizer::query()->create([
            'user_id' => $organizerUser->id,
            'business_name' => 'Test Collective',
            'slug' => 'test-collective',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        Wallet::query()->create([
            'organizer_id' => $organizer->id,
            'currency' => 'UGX',
            'balance_minor' => 0,
        ]);

        $this->listing = Listing::query()->create([
            'organizer_id' => $organizer->id,
            'type' => 'event',
            'slug' => 'test-event',
            'title' => 'Test Event',
            'starts_at' => now()->addDay(),
            'status' => 'published',
            'published_at' => now(),
            'max_per_order' => 5,
            'refund_window_hours' => 72,
        ]);

        $this->tier = TicketType::query()->create([
            'listing_id' => $this->listing->id,
            'name' => 'Regular',
            'price_minor' => 10_000,
            'currency' => 'UGX',
            'capacity' => 5,
            'max_per_order' => 5,
            'active' => true,
        ]);

        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    public function test_marketplace_lists_published_listings(): void
    {
        $this->actingAs($this->buyer)->get(route('home'))->assertOk()->assertSee('Test Event');
    }

    public function test_checkout_holds_inventory_and_issues_tickets_after_payment(): void
    {
        $response = $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 2,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $response->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(20_000, $order->total_minor);
        $this->assertSame(2, (int) $this->tier->fresh()->held_count);
        $this->assertSame(0, (int) $this->tier->fresh()->sold_count);

        /** @var DemoMomoGateway $gateway */
        $gateway = app(DemoMomoGateway::class);
        $gateway->approve($order->tx_ref);

        $this->actingAs($this->buyer)
            ->get(route('checkout.return', ['reference' => $order->tx_ref]))
            ->assertRedirect(route('buyer.orders.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(2, (int) $this->tier->fresh()->sold_count);
        $this->assertSame(0, (int) $this->tier->fresh()->held_count);

        $this->assertSame(2, Ticket::query()->count());
        $this->assertNotNull(Ticket::query()->first()->qr_code_hash);

        // 20% commission, 80% organizer share.
        $this->assertSame(4_000, $order->commission_minor);
        $this->assertSame(16_000, Wallet::query()->first()->balance_minor);
    }

    public function test_demo_approval_route_completes_the_checkout(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 2,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => $this->buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->actingAs($this->buyer)
            ->get(route('checkout.demo', $order->tx_ref))
            ->assertOk()
            ->assertSee($order->tx_ref);

        $this->actingAs($this->buyer)
            ->post(route('checkout.demo.approve', $order->tx_ref), ['pin' => '1234'])
            ->assertRedirect(route('checkout.return', ['reference' => $order->tx_ref]));

        $this->actingAs($this->buyer)
            ->get(route('checkout.return', ['reference' => $order->tx_ref]))
            ->assertRedirect(route('buyer.orders.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame(16_000, Wallet::query()->first()->balance_minor);

        $this->actingAs($this->buyer)
            ->get(route('buyer.tickets'))
            ->assertOk()
            ->assertSee(Ticket::query()->first()->code);
    }

    public function test_a_service_slot_is_booked_and_paid_for(): void
    {
        $serviceListing = Listing::query()->create([
            'organizer_id' => $this->listing->organizer_id,
            'type' => 'service',
            'slug' => 'studio-session',
            'title' => 'Studio Session',
            'starts_at' => now()->addDay(),
            'status' => 'published',
            'published_at' => now(),
            'max_per_order' => 2,
            'refund_window_hours' => 48,
        ]);

        $service = Service::query()->create([
            'listing_id' => $serviceListing->id,
            'name' => 'Recording + mixing',
            'duration_minutes' => 45,
            'price_minor' => 15_000,
            'capacity_per_slot' => 1,
        ]);

        $slot = ServiceSlot::query()->create([
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(9, 45),
            'capacity' => 1,
            'status' => 'open',
        ]);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $serviceListing->id,
            'service_slot_id' => $slot->id,
            'quantity' => 1,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => $this->buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame(15_000, $order->total_minor);
        $this->assertSame(1, (int) $slot->fresh()->held_count);

        $this->actingAs($this->buyer)
            ->post(route('checkout.demo.approve', $order->tx_ref), ['pin' => '1234'])
            ->assertRedirect();

        $this->actingAs($this->buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $order->refresh();
        $slot->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame(1, (int) $slot->booked_count);
        $this->assertSame(0, (int) $slot->held_count);
        $this->assertSame('booked', $slot->status);

        // A service booking is an appointment, not a QR ticket.
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(12_000, Wallet::query()->first()->balance_minor);
        $this->assertSame(3_000, $order->commission_minor);
    }

    public function test_inventory_cannot_be_oversold(): void
    {
        $other = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 4,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $this->actingAs($other)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 4,
            'buyer_name' => 'Ama Serwaa',
            'buyer_email' => 'ama@example.test',
            'buyer_phone' => '0756123456',
            'momo_provider' => 'airtel',
        ])->assertSessionHas('error');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_a_provider_that_is_not_collecting_is_refused_and_the_buyer_is_told_what_to_use(): void
    {
        config([
            'ticketing.momo.providers' => ['mtn' => 'MTN Mobile Money'],
            'ticketing.momo.unavailable_providers' => ['airtel' => 'Airtel Money'],
        ]);

        $this->get(route('events.show', $this->listing->slug))
            ->assertOk()
            ->assertSee('Airtel Money — coming soon')
            ->assertSee('Pay with MTN Mobile Money');

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 1,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'airtel',
        ])->assertSessionHasErrors('momo_provider');

        $this->assertStringContainsString('MTN Mobile Money', (string) session('errors')->first('momo_provider'));
        $this->assertSame(0, Order::query()->count());
    }

    public function test_webhook_is_idempotent_and_marks_the_order_paid(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 1,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $order = Order::query()->firstOrFail();

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $order->tx_ref,
                'amount' => Money::toGatewayAmount($order->total_minor, 'UGX'),
                'currency' => 'UGX',
            ],
        ];

        $this->postSignedWebhook($payload)->assertOk()->assertJson(['status' => true]);
        $this->postSignedWebhook($payload)->assertOk()->assertJson(['message' => 'duplicate ignored']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_scanner_rejects_a_ticket_belonging_to_another_organizer(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 1,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $order = Order::query()->firstOrFail();
        app(DemoMomoGateway::class)->approve($order->tx_ref);
        $this->actingAs($this->buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $ticket = Ticket::query()->firstOrFail();

        $otherOrganizerUser = User::factory()->create(['role' => 'organizer']);
        $otherOrganizer = Organizer::query()->create([
            'user_id' => $otherOrganizerUser->id,
            'business_name' => 'Other Collective',
            'slug' => 'other-collective',
            'status' => 'approved',
        ]);

        $this->actingAs($otherOrganizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'wrong_event']);
    }

    public function test_scanner_accepts_a_valid_ticket_once(): void
    {
        $organizerUser = User::query()->whereHas('organizer', fn ($q) => $q->where('slug', 'test-collective'))->firstOrFail();

        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 1,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        $order = Order::query()->firstOrFail();
        app(DemoMomoGateway::class)->approve($order->tx_ref);
        $this->actingAs($this->buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($organizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'valid']);

        $this->assertSame('used', $ticket->fresh()->status);

        $this->actingAs($organizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'already_used']);
    }

    public function test_admin_can_change_the_platform_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.settings.commission'), ['commission_percent' => 5])
            ->assertRedirect();

        $this->assertSame('500', setting('platform_commission_bps'));
    }

    public function test_role_middleware_isolates_the_admin_area(): void
    {
        $this->actingAs($this->buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('organizer.dashboard'))->assertForbidden();
    }

    public function test_expired_holds_release_inventory(): void
    {
        $this->actingAs($this->buyer)->post(route('checkout.store'), [
            'listing_id' => $this->listing->id,
            'ticket_type_id' => $this->tier->id,
            'quantity' => 2,
            'buyer_name' => 'Kojo Mensah',
            'buyer_email' => 'kojo@example.test',
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ]);

        Order::query()->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();

        Cache::forget('ticketing:sweep:lock');

        $this->actingAs($this->buyer)->get(route('home'))->assertOk();

        $this->assertSame('expired', Order::query()->firstOrFail()->status);
        $this->assertSame(0, (int) $this->tier->fresh()->held_count);
    }

    /**
     * Post a provider callback the way the provider signs it: the raw body
     * with the HMAC-SHA512 signature of the configured secret.
     *
     * @param  array<string,mixed>  $payload
     */
    private function postSignedWebhook(array $payload): TestResponse
    {
        config(['ticketing.paystack.secret_key' => self::SECRET]);

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            route('api.v1.momo.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $raw, self::SECRET),
            ],
            $raw
        );
    }
}
