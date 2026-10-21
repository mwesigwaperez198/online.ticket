<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use RuntimeException;

/**
 * Bound instead of the demo gateway when a production deployment is missing
 * live Mobile Money credentials.
 *
 * The gateway never authorises a payment, so an order can only be settled by
 * a real provider callback that passed signature verification. No wallet is
 * ever credited and no entry pass is ever issued from this binding.
 */
final class UnavailableMomoGateway implements MomoGateway
{
    public function name(): string
    {
        return 'unavailable';
    }

    public function initialize(array $payload): array
    {
        throw new RuntimeException('Mobile Money payments are not configured on this deployment.');
    }

    public function verify(string $txRef): ?array
    {
        return null;
    }

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool
    {
        return false;
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return false;
    }
}
