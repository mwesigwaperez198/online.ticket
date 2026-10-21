<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\PaystackMomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class MomoWebhookTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    private const SECRET = 'sk_test_novara_secret_key';

    public function test_a_signed_webhook_marks_the_order_paid_and_splits_the_funds(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, ['price_minor' => Money::toMinor(50_000)]);

        $buyer = $this->createBuyer();

        $order = $this->checkout($buyer, $listing, $tier);

        $this->assertSame('pending', $order->status);

        $this->postSignedWebhook($this->successPayload($order))->assertOk()->assertJson(['status' => true]);

        $order->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('issued', $ticket->status);
        $this->assertSame(64, strlen((string) $ticket->qr_code_hash));
        $this->assertSame('Ivan Kaggwa', $ticket->holder_name);

        // The default platform fee is 20%, so the organizer keeps 80%.
        $this->assertSame(2000, $order->commission_bps_applied);
        $this->assertSame(Money::toMinor(10_000), $order->commission_minor);
        $this->assertSame(Money::toMinor(40_000), $organizer->wallet()->firstOrFail()->balance_minor);

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('success', $payment->status);
        $this->assertSame('paystack', $payment->gateway);
        $this->assertSame($order->total_minor, $payment->amount_minor);

        $this->assertSame(1, WalletTransaction::query()->where('type', 'sale')->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'fee')->count());
    }

    public function test_a_webhook_without_a_valid_signature_is_rejected(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $order = $this->checkout($buyer, $listing, $tier);

        $raw = json_encode($this->successPayload($order), JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('api.v1.momo.webhook'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'not-the-right-signature'],
            $raw
        )->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_a_repeated_webhook_is_ignored(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $order = $this->checkout($buyer, $listing, $tier);

        $this->postSignedWebhook($this->successPayload($order))->assertOk();
        $this->postSignedWebhook($this->successPayload($order))->assertOk()->assertJson(['message' => 'duplicate ignored']);

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(Money::toMinor(40_000), $organizer->wallet()->firstOrFail()->balance_minor);
    }

    public function test_a_webhook_with_a_different_amount_does_not_pay_the_order(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $order = $this->checkout($buyer, $listing, $tier);

        $payload = $this->successPayload($order);
        $payload['data']['amount'] = Money::toGatewayAmount($order->total_minor - 100, 'UGX');

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, (int) $organizer->wallet()->firstOrFail()->balance_minor);
    }

    public function test_a_webhook_for_an_unknown_reference_is_acknowledged_but_ignored(): void
    {
        $this->bindPaystackGateway();

        $this->postSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => 'TKUNKNOWNREFERENCE00',
                'amount' => 50_000,
                'currency' => 'UGX',
            ],
        ])->assertOk()->assertJson(['status' => true]);

        $this->assertSame(0, Order::query()->count());
    }

    private function checkout(User $buyer, Listing $listing, TicketType $tier): Order
    {
        $this->bindPaystackGateway();

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        return Order::query()->firstOrFail();
    }

    private function bindPaystackGateway(): void
    {
        // The webhook middleware verifies the signature with the configured
        // provider secret, so the test has to configure the same one.
        config(['ticketing.paystack.secret_key' => self::SECRET]);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'reference' => 'ps-ref-'.strtoupper(Str::random(8)),
                ],
            ], 200),
        ]);

        $this->app->instance(
            MomoGateway::class,
            new PaystackMomoGateway(self::SECRET, 'https://api.paystack.co', 'UGX')
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function successPayload(Order $order): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $order->tx_ref,
                'amount' => Money::toGatewayAmount($order->total_minor, 'UGX'),
                'currency' => 'UGX',
                'channel' => 'mobile_money',
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function postSignedWebhook(array $payload): TestResponse
    {
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
