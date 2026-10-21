<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\WalletTransaction;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\MtnMomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

/**
 * The live MTN MoMo Collections round trip.
 *
 * MTN authorises the charge on the buyer's handset, so the platform asks MTN
 * to collect, stores the payment id and then waits for the result
 * notification. The notification carries no signature, so the charge is
 * confirmed with a server to server status lookup before anything settles.
 */
final class MtnMomoTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    private const BASE_URL = 'https://sandbox.momodeveloper.mtn.com';

    private const PRIMARY_KEY = 'primary-subscription-key';

    private const SECONDARY_KEY = 'secondary-subscription-key';

    private const TARGET_ENVIRONMENT = 'UgxAndMore';

    private const PAYMENT_ID = 'MNP_1700000000000';

    private const CALLBACK_URL = 'https://online-ticket-c5mz.onrender.com/api/v1/momo/webhook';

    private ?Organizer $organizer = null;

    public function test_the_checkout_asks_mtn_to_collect_the_charge(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        Http::assertSent(function (Request $request) use ($order): bool {
            if (! str_ends_with($request->url(), '/collection/v1_0/request-payment')) {
                return false;
            }

            $body = $request->data();

            // The subscription key travels in the header MTN expects.
            $this->assertSame(self::PRIMARY_KEY, $this->header($request, 'Ocp-Apim-Subscription-Key'));
            $this->assertSame('Bearer '.self::TARGET_ENVIRONMENT, $this->header($request, 'Authorization'));
            $this->assertSame(self::TARGET_ENVIRONMENT, $this->header($request, 'X-Target-Environment'));
            $this->assertSame($order->tx_ref, $this->header($request, 'X-Reference'));
            $this->assertSame(self::CALLBACK_URL, $this->header($request, 'X-Callback-Url'));

            // Ugandan shillings are collected in whole shillings.
            $this->assertSame('50000.00', $body['amount']);
            $this->assertSame('UGX', $body['currency']);
            $this->assertSame('256771234567', $body['msisdn']);
            $this->assertSame($order->tx_ref, $body['externalId']);
            $this->assertSame(self::CALLBACK_URL, $body['callbackUrl']);

            return true;
        });
    }

    public function test_the_secondary_subscription_key_is_used_when_the_primary_one_is_rejected(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/collection/v1_0/request-payment')) {
                if ($this->header($request, 'Ocp-Apim-Subscription-Key') === self::PRIMARY_KEY) {
                    return Http::response(['message' => 'Access denied'], 401);
                }

                return Http::response([
                    'status' => 'SUCCESS',
                    'paymentId' => self::PAYMENT_ID,
                ], 201);
            }

            return Http::response(['status' => 'SUCCESS'], 200);
        });

        $order = $this->checkout();

        $this->assertSame('pending', $order->status);
        $this->assertSame(self::PAYMENT_ID, $order->gateway_reference);

        Http::assertSent(fn (Request $request): bool => $this->header($request, 'Ocp-Apim-Subscription-Key') === self::SECONDARY_KEY);
    }

    public function test_the_buyer_is_sent_to_the_prompt_screen_with_the_payment_id_stored(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        $this->assertSame(self::PAYMENT_ID, $order->gateway_reference);
        $this->assertSame('mtn', $order->gateway);

        $this->actingAs($order->user)
            ->get(route('checkout.prompt', ['tx_ref' => $order->tx_ref]))
            ->assertOk()
            ->assertSee('Check your phone')
            ->assertSee($order->tx_ref);

        // The status the prompt screen polls.
        $this->actingAs($order->user)
            ->get(route('checkout.status', ['tx_ref' => $order->tx_ref]))
            ->assertOk()
            ->assertJson(['status' => 'pending', 'paid' => false]);
    }

    public function test_the_prompt_screen_is_scoped_to_the_buyer(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        $this->actingAs($this->createBuyer())
            ->get(route('checkout.prompt', ['tx_ref' => $order->tx_ref]))
            ->assertNotFound();

        $this->actingAs($this->createBuyer())
            ->get(route('checkout.status', ['tx_ref' => $order->tx_ref]))
            ->assertNotFound();
    }

    public function test_an_mtn_notification_settles_the_order_once_the_charge_is_confirmed(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        $organizer = $this->organizer;

        $this->postNotification($order)->assertOk()->assertJson(['status' => true]);

        $order->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('issued', $ticket->status);
        $this->assertSame(64, strlen((string) $ticket->qr_code_hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $ticket->qr_code_hash);

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('success', $payment->status);
        $this->assertSame('mtn', $payment->gateway);
        $this->assertSame($order->total_minor, $payment->amount_minor);

        // 20% platform commission, 80% organizer share.
        $this->assertSame(2000, $order->commission_bps_applied);
        $this->assertSame(Money::toMinor(10_000), $order->commission_minor);
        $this->assertSame(Money::toMinor(40_000), $organizer->wallet()->firstOrFail()->balance_minor);
        $this->assertSame(1, WalletTransaction::query()->where('type', 'sale')->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', 'fee')->count());
    }

    public function test_a_notification_for_a_payment_that_does_not_belong_to_the_order_is_ignored(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        $this->postNotification($order, ['paymentId' => 'MNP_SOMEONE_ELSE'])->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_notification_with_a_different_amount_does_not_settle_the_order(): void
    {
        $this->fakeMtn(['amount' => '1.00']);

        $order = $this->checkout();

        $this->postNotification($order)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, (int) $this->organizer->wallet()->firstOrFail()->balance_minor);
    }

    public function test_a_repeated_notification_is_ignored(): void
    {
        $this->fakeMtn();

        $order = $this->checkout();

        $this->postNotification($order)->assertOk();
        $this->postNotification($order)->assertOk()->assertJson(['message' => 'duplicate ignored']);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(Money::toMinor(40_000), $this->organizer->wallet()->firstOrFail()->balance_minor);
    }

    private function checkout(): Order
    {
        $this->organizer = $this->createOrganizer();
        $listing = $this->createListing($this->organizer);
        $tier = $this->createTicketType($listing, ['price_minor' => Money::toMinor(50_000)]);

        $buyer = $this->createBuyer();

        $this->app->instance(MomoGateway::class, $this->gateway());

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

    private function gateway(): MtnMomoGateway
    {
        return new MtnMomoGateway(
            self::PRIMARY_KEY,
            self::SECONDARY_KEY,
            self::BASE_URL,
            self::TARGET_ENVIRONMENT,
            'UGX',
            'Novara',
            self::CALLBACK_URL,
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function fakeMtn(array $overrides = []): void
    {
        Http::fake(function (Request $request) use ($overrides) {
            if (str_contains($request->url(), '/request-payment/')) {
                return Http::response([
                    'status' => 'SUCCESS',
                    'amount' => $overrides['amount'] ?? '50000.00',
                    'currency' => 'UGX',
                    'paymentId' => self::PAYMENT_ID,
                    'externalId' => $overrides['external_id'] ?? '',
                ]);
            }

            return Http::response([
                'status' => 'SUCCESS',
                'payItemId' => 'pay-item-1',
                'paymentId' => self::PAYMENT_ID,
                'reference' => self::PAYMENT_ID,
            ], 201);
        });
    }

    /**
     * The value of a request header sent to the provider.
     */
    private function header(Request $request, string $name): ?string
    {
        $values = $request->header($name);

        return is_array($values) ? ($values[0] ?? null) : $values;
    }

    /**
     * The result notification MTN pushes to the callback URL.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function postNotification(Order $order, array $overrides = [])
    {
        config(['ticketing.momo.webhook_providers' => ['mtn']]);

        $payload = array_merge([
            'amount' => '50000.00',
            'currency' => 'UGX',
            'financialTransactionId' => '3373307',
            'externalId' => $order->tx_ref,
            'paymentId' => self::PAYMENT_ID,
        ], $overrides);

        return $this->postJson(route('api.v1.momo.webhook'), $payload);
    }
}
