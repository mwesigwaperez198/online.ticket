<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use App\Support\WebhookPayload;
use App\Support\WebhookSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settles recorded Mobile Money webhook receipts.
 *
 * The webhook endpoint only verifies the callback, stores the receipt and
 * answers 200: Render free instances cut requests at 50 seconds, so the
 * provider confirmation lookup and the ledger write run after the response
 * has been flushed. Everything a settlement needs lives in the
 * `webhook_events` row, so a settlement interrupted by a recycled
 * container can be driven again from the stored payload without any local
 * state.
 */
final class WebhookSettlement
{
    /**
     * Receipts younger than this may still be settling inside the request
     * that acknowledged them.
     */
    private const GRACE_SECONDS = 15;

    /**
     * Minutes after which an open receipt will never settle (a dead
     * provider route, a broken configuration) and is closed for good so
     * the sweep stops spending on it.
     */
    private const GIVE_UP_MINUTES = 60;

    public function __construct(
        private readonly MomoGateway $gateway,
        private readonly PaymentProcessor $processor,
    ) {}

    /**
     * Settle one receipt. Safe to run more than once: a processed receipt,
     * an order that is already paid and a rejected payload all short
     * circuit without touching the ledger.
     */
    public function settle(WebhookEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }

        $payload = $event->payload;

        if (! is_array($payload)) {
            $this->markProcessed($event);

            return;
        }

        $provider = $event->provider;
        $confirmation = WebhookSignature::requiresConfirmation($provider);

        try {
            DB::transaction(function () use ($event, $payload, $provider, $confirmation): void {
                $order = Order::query()->where('tx_ref', $event->event_id)->first();

                if ($order === null) {
                    Log::warning('momo webhook for unknown tx_ref', [
                        'tx_ref' => $event->event_id,
                        'provider' => $provider,
                    ]);

                    $this->markProcessed($event);

                    return;
                }

                if ($order->isPaid()) {
                    $this->markProcessed($event);

                    return;
                }

                $data = WebhookPayload::normalize($payload);

                $result = $confirmation
                    ? $this->confirmWithProvider($order, $data)
                    : $this->settleFromPayload($order, $data);

                if ($result['settlement'] === null) {
                    if (! $result['transient']) {
                        // The payload was rejected for good: closing the
                        // receipt keeps the sweep from spending on it again.
                        $this->markProcessed($event);
                    }

                    return;
                }

                $this->processor->markPaid($order, $result['settlement'], $provider);

                $this->markProcessed($event);
            }, 3);
        } catch (Throwable $e) {
            // The receipt stays open so a later sweep can drive it again.
            Log::error('momo webhook settlement failed', [
                'tx_ref' => $event->event_id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-drive receipts whose settlement never finished and close the ones
     * that never will.
     *
     * Render free has no queue worker, so this runs opportunistically on
     * inbound requests behind the sweep lock.
     */
    public function retryUnsettled(int $limit = 10): int
    {
        $open = WebhookEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subSeconds(self::GRACE_SECONDS))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $finished = 0;

        foreach ($open as $event) {
            if ($event->created_at !== null && $event->created_at->lte(now()->subMinutes(self::GIVE_UP_MINUTES))) {
                Log::warning('momo webhook settlement gave up', [
                    'tx_ref' => $event->event_id,
                    'provider' => $event->provider,
                ]);

                $this->markProcessed($event);

                continue;
            }

            $this->settle($event);

            if ($event->processed_at !== null) {
                $finished++;
            }
        }

        return $finished;
    }

    /**
     * The provider signed the payload, so the payload is the proof.
     *
     * @param  array<string,mixed>  $data
     * @return array{settlement: array<string,mixed>|null, transient: bool}
     */
    private function settleFromPayload(Order $order, array $data): array
    {
        $amount = $data['amount'];

        if ($amount === null || ! Money::matchesAmount($amount, $order->total_minor, $order->currency)) {
            Log::warning('momo webhook amount mismatch', [
                'tx_ref' => $order->tx_ref,
                'expected' => $order->total_minor,
                'received' => $amount,
            ]);

            return ['settlement' => null, 'transient' => false];
        }

        return [
            'settlement' => [
                'reference' => $order->tx_ref,
                'amount' => $amount,
                'currency' => $data['currency'] ?? $order->currency,
                'channel' => $data['channel'] ?? 'mobile_money',
                'paid_at' => $data['paid_at'] ?? now()->toIso8601String(),
            ],
            'transient' => false,
        ];
    }

    /**
     * The provider does not sign its notifications, so the charge is proven
     * with a server to server status lookup.
     *
     * The payment id has to be the one the platform stored when it asked
     * the provider to collect, and the confirmed transaction has to carry
     * the same reference and the same amount as the order. A lookup that
     * did not go through, or a provider that has not reached a verdict yet,
     * keeps the receipt open so the sweep retries it.
     *
     * @param  array<string,mixed>  $data
     * @return array{settlement: array<string,mixed>|null, transient: bool}
     */
    private function confirmWithProvider(Order $order, array $data): array
    {
        $paymentId = (string) ($data['payment_id'] ?? '');

        if ($paymentId === '' || $paymentId !== (string) $order->gateway_reference) {
            Log::warning('momo webhook payment id does not belong to the order', [
                'tx_ref' => $order->tx_ref,
                'payment_id' => $paymentId,
            ]);

            return ['settlement' => null, 'transient' => false];
        }

        $confirmation = $this->gateway->verify($paymentId);

        if (! is_array($confirmation)) {
            return ['settlement' => null, 'transient' => true];
        }

        $status = (string) ($confirmation['status'] ?? '');

        if (in_array($status, ['pending', 'submitted', 'processing'], true)) {
            return ['settlement' => null, 'transient' => true];
        }

        if ($status !== 'success') {
            Log::info('momo webhook could not be confirmed with the provider', [
                'tx_ref' => $order->tx_ref,
                'status' => $status,
            ]);

            return ['settlement' => null, 'transient' => false];
        }

        $externalId = (string) ($confirmation['external_id'] ?? '');

        if ($externalId !== '' && $externalId !== $order->tx_ref) {
            Log::warning('momo webhook confirmation carries a different reference', [
                'tx_ref' => $order->tx_ref,
                'external_id' => $externalId,
            ]);

            return ['settlement' => null, 'transient' => false];
        }

        $amount = $confirmation['amount'] ?? $data['amount'];

        if ($amount === null || ! Money::matchesAmount($amount, $order->total_minor, $order->currency)) {
            Log::warning('momo webhook confirmed amount mismatch', [
                'tx_ref' => $order->tx_ref,
                'expected' => $order->total_minor,
                'received' => $amount,
            ]);

            return ['settlement' => null, 'transient' => false];
        }

        return [
            'settlement' => [
                'reference' => $paymentId,
                'amount' => $amount,
                'currency' => $confirmation['currency'] ?? ($data['currency'] ?? $order->currency),
                'channel' => $confirmation['channel'] ?? 'mobile_money',
                'paid_at' => $confirmation['paid_at'] ?? now()->toIso8601String(),
            ],
            'transient' => false,
        ];
    }

    private function markProcessed(WebhookEvent $event): void
    {
        $event->forceFill(['processed_at' => now()])->save();
    }
}
