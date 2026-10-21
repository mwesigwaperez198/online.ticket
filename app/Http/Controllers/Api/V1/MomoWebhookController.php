<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentProcessor;
use App\Support\Money;
use App\Support\WebhookPayload;
use App\Support\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MomoWebhookController extends Controller
{
    public function __invoke(Request $request, MomoGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        $provider = $request->attributes->get(WebhookSignature::ATTRIBUTE);

        // The signature middleware owns verification. A request that did not
        // pass it never reaches the settlement code.
        if (! is_string($provider) || $provider === '') {
            Log::warning('momo webhook reached the controller without a verified signature', ['ip' => $request->ip()]);

            return response()->json(['status' => false, 'message' => 'invalid signature'], 401);
        }

        $confirmation = (bool) $request->attributes->get(WebhookSignature::CONFIRMATION_ATTRIBUTE);

        $raw = $request->getContent();

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['status' => false, 'message' => 'invalid json'], 422);
        }

        if (! is_array($payload)) {
            return response()->json(['status' => false, 'message' => 'invalid json'], 422);
        }

        $data = WebhookPayload::normalize($payload);

        if (! $confirmation && $data['status'] !== 'success') {
            Log::info('momo webhook ignored', ['status' => $data['status'], 'provider' => $provider]);

            return response()->json(['status' => true, 'message' => 'ignored']);
        }

        $txRef = $confirmation
            ? (string) ($data['external_id'] ?? $data['reference'])
            : $data['reference'];

        if ($txRef === '') {
            return response()->json(['status' => false, 'message' => 'missing reference'], 422);
        }

        $duplicate = false;

        DB::transaction(function () use ($txRef, $data, $payload, $provider, $confirmation, $gateway, $processor, &$duplicate): void {
            /*
             * The unique index on (provider, event_id) is the single source of
             * truth for idempotency. A replayed callback loses the insert race
             * and is acknowledged without touching the ledger.
             */
            $recorded = WebhookEvent::query()->insertOrIgnore([
                'provider' => $provider,
                'event_id' => $txRef,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($recorded === 0) {
                $duplicate = true;

                return;
            }

            $order = Order::query()->where('tx_ref', $txRef)->first();

            if ($order === null) {
                Log::warning('momo webhook for unknown tx_ref', ['tx_ref' => $txRef, 'provider' => $provider]);

                return;
            }

            if ($order->isPaid()) {
                Log::info('momo webhook for an order that is already settled', ['tx_ref' => $txRef]);

                return;
            }

            $settlement = $confirmation
                ? $this->confirmWithProvider($order, $data, $gateway)
                : $this->settleFromPayload($order, $data);

            if ($settlement === null) {
                return;
            }

            $processor->markPaid($order, $settlement, $provider);
        }, 3);

        if ($duplicate) {
            return response()->json(['status' => true, 'message' => 'duplicate ignored']);
        }

        return response()->json(['status' => true, 'message' => 'ok']);
    }

    /**
     * The provider signed the payload, so the payload is the proof.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>|null
     */
    private function settleFromPayload(Order $order, array $data): ?array
    {
        $amount = $data['amount'];

        if ($amount === null || ! Money::matchesAmount($amount, $order->total_minor, $order->currency)) {
            Log::warning('momo webhook amount mismatch', [
                'tx_ref' => $order->tx_ref,
                'expected' => $order->total_minor,
                'received' => $amount,
            ]);

            return null;
        }

        return [
            'reference' => $order->tx_ref,
            'amount' => $amount,
            'currency' => $data['currency'] ?? $order->currency,
            'channel' => $data['channel'] ?? 'mobile_money',
            'paid_at' => $data['paid_at'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * The provider does not sign its notifications, so the charge is proven
     * with a server to server status lookup.
     *
     * The payment id has to be the one the platform stored when it asked the
     * provider to collect, and the confirmed transaction has to carry the
     * same reference and the same amount as the order.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>|null
     */
    private function confirmWithProvider(Order $order, array $data, MomoGateway $gateway): ?array
    {
        $paymentId = (string) ($data['payment_id'] ?? '');

        if ($paymentId === '' || $paymentId !== (string) $order->gateway_reference) {
            Log::warning('momo webhook payment id does not belong to the order', [
                'tx_ref' => $order->tx_ref,
                'payment_id' => $paymentId,
            ]);

            return null;
        }

        $confirmation = $gateway->verify($paymentId);

        if (! is_array($confirmation) || ($confirmation['status'] ?? null) !== 'success') {
            Log::info('momo webhook could not be confirmed with the provider', [
                'tx_ref' => $order->tx_ref,
                'status' => is_array($confirmation) ? ($confirmation['status'] ?? null) : null,
            ]);

            return null;
        }

        $externalId = (string) ($confirmation['external_id'] ?? '');

        if ($externalId !== '' && $externalId !== $order->tx_ref) {
            Log::warning('momo webhook confirmation carries a different reference', [
                'tx_ref' => $order->tx_ref,
                'external_id' => $externalId,
            ]);

            return null;
        }

        $amount = $confirmation['amount'] ?? $data['amount'];

        if ($amount === null || ! Money::matchesAmount($amount, $order->total_minor, $order->currency)) {
            Log::warning('momo webhook confirmed amount mismatch', [
                'tx_ref' => $order->tx_ref,
                'expected' => $order->total_minor,
                'received' => $amount,
            ]);

            return null;
        }

        return [
            'reference' => $paymentId,
            'amount' => $amount,
            'currency' => $confirmation['currency'] ?? ($data['currency'] ?? $order->currency),
            'channel' => $confirmation['channel'] ?? 'mobile_money',
            'paid_at' => $confirmation['paid_at'] ?? now()->toIso8601String(),
        ];
    }
}
