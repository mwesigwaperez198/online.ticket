<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\MtnMomoGateway;
use App\Services\WebhookSettlement;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

/**
 * The webhook answers the gateway before it settles anything, so these
 * tests pin the receipt lifecycle the deferred settlement depends on: an
 * interrupted settlement is driven again from the database, a rejected
 * payload is closed for good, and a provider that has not reached a
 * verdict yet keeps the receipt open until it does.
 */
final class WebhookSettlementTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    private const SECRET = 'sk_test_novara_secret_key';

    public function test_the_sweep_re_drives_a_receipt_whose_settlement_never_finished(): void
    {
        $order = $this->pendingOrder();

        $this->createOpenReceipt($order, $this->successPayload($order));

        Cache::forget('ticketing:sweep:lock');

        $this->get(route('home'))->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    public function test_a_rejected_payload_closes_its_receipt_so_the_sweep_never_retries_it(): void
    {
        $order = $this->pendingOrder();

        $payload = $this->successPayload($order);
        $payload['data']['amount'] = Money::toGatewayAmount($order->total_minor - 100, 'UGX');

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    public function test_a_transient_confirmation_failure_keeps_the_receipt_open_until_the_provider_says_yes(): void
    {
        config([
            'ticketing.momo.webhook_providers' => ['mtn'],
            'ticketing.momo.mtn.webhook_secret' => '',
        ]);

        $order = $this->pendingOrder();
        $order->forceFill(['gateway_reference' => 'mtn-pay-001'])->save();

        $confirmed = false;

        Http::fake(function () use (&$confirmed, $order): mixed {
            return Http::response([
                'status' => $confirmed ? 'SUCCESS' : 'PENDING',
                'paymentId' => 'mtn-pay-001',
                'externalId' => $order->tx_ref,
                'amount' => Money::toGatewayAmount($order->total_minor, 'UGX'),
                'currency' => 'UGX',
            ]);
        });

        $this->app->instance(MomoGateway::class, new MtnMomoGateway(
            'primary-key',
            'secondary-key',
            'https://sandbox.momodeveloper.mtn.com',
            'UgxAndMore',
            'UGX',
            'Novara',
            '',
        ));

        $this->postMomoWebhook([
            'status' => 'SUCCESS',
            'paymentId' => 'mtn-pay-001',
            'externalId' => $order->tx_ref,
            'amount' => Money::toGatewayAmount($order->total_minor, 'UGX'),
            'currency' => 'UGX',
        ])->assertOk()->assertJson(['status' => true]);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull(WebhookEvent::query()->firstOrFail()->processed_at);

        // MTN reaches its verdict after the acknowledgement went out.
        $confirmed = true;

        WebhookEvent::query()->update(['created_at' => now()->subMinute()]);

        $this->app->make(WebhookSettlement::class)->retryUnsettled();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    /**
     * A pending order with the inventory hold the checkout would have taken.
     */
    private function pendingOrder(): Order
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, ['price_minor' => Money::toMinor(50_000)]);
        $buyer = $this->createBuyer();

        $tier->forceFill(['held_count' => 1])->save();

        $order = Order::query()->create([
            'tx_ref' => 'TX-'.Str::upper(Str::random(20)),
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'type' => 'event',
            'status' => 'pending',
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
            'expires_at' => now()->addMinutes(10),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $tier->id,
            'unit_price_minor' => Money::toMinor(50_000),
            'quantity' => 1,
            'line_total_minor' => Money::toMinor(50_000),
        ]);

        return $order;
    }

    /**
     * A receipt that was acknowledged but never finished settling, exactly
     * as a recycled Render container would leave it behind.
     *
     * @param  array<string,mixed>  $payload
     */
    private function createOpenReceipt(Order $order, array $payload): WebhookEvent
    {
        $event = new WebhookEvent([
            'provider' => 'paystack',
            'event_id' => $order->tx_ref,
            'payload' => $payload,
            'processed_at' => null,
        ]);

        // Backdated so the sweep treats it as an interrupted settlement
        // rather than one still running in the acknowledging request.
        $event->created_at = now()->subMinute();
        $event->save();

        return $event;
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

    /**
     * Post an MTN notification. It carries no signature, so the endpoint
     * proves the charge with a server to server lookup instead.
     *
     * @param  array<string,mixed>  $payload
     */
    private function postMomoWebhook(array $payload): TestResponse
    {
        return $this->call(
            'POST',
            route('api.v1.momo.webhook'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }
}
