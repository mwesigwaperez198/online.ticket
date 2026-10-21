<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use Illuminate\Support\Facades\Cache;

/**
 * Local demo gateway used when no Paystack secret key is configured.
 *
 * It mimics the Mobile Money authorization round trip so the complete
 * checkout, ledger and ticket issuance flow can be exercised end to end
 * before live gateway credentials exist.
 */
final class DemoMomoGateway implements MomoGateway
{
    public function name(): string
    {
        return 'demo';
    }

    public function initialize(array $payload): array
    {
        $txRef = (string) $payload['tx_ref'];

        Cache::put('demo:tx:'.$txRef, [
            'amount' => $payload['amount_minor'],
            'currency' => $payload['currency'] ?? 'UGX',
            'phone' => $payload['phone'] ?? null,
            'approved' => false,
        ], now()->addMinutes(30));

        return [
            'authorization_url' => route('checkout.demo', ['tx_ref' => $txRef]),
            'reference' => $txRef,
            'raw' => ['status' => 'initialize', 'reference' => $txRef],
        ];
    }

    public function verify(string $txRef): ?array
    {
        $state = Cache::get('demo:tx:'.$txRef);

        if (! is_array($state) || ($state['approved'] ?? false) !== true) {
            return null;
        }

        return [
            'status' => 'success',
            'reference' => $txRef,
            'amount' => $state['amount'],
            'currency' => $state['currency'] ?? 'UGX',
            'channel' => 'mobile_money',
            'paid_at' => now()->toIso8601String(),
        ];
    }

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool
    {
        return true;
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return true;
    }

    public function approve(string $txRef): void
    {
        $state = Cache::get('demo:tx:'.$txRef, []);
        $state['approved'] = true;

        Cache::put('demo:tx:'.$txRef, $state, now()->addMinutes(30));
    }
}
