<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisation of Mobile Money webhook payloads.
 *
 * Every aggregator reports a successful charge in its own shape, so the
 * payload is flattened into one canonical record before the order is settled:
 * a status, the platform transaction reference, the amount and the currency.
 */
final class WebhookPayload
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array{status:string, reference:string, payment_id:string|null, external_id:string|null, amount:int|null, currency:string|null, channel:string|null, paid_at:string|null}
     */
    public static function normalize(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);

        $status = strtolower(trim((string) ($data['status'] ?? $payload['status'] ?? '')));
        $reference = (string) ($data['reference'] ?? $data['tx_ref'] ?? $data['txRef']
            ?? $payload['externalId'] ?? $payload['reference'] ?? '');
        $paymentId = (string) ($data['payment_id'] ?? $data['paymentId'] ?? $payload['paymentId'] ?? $payload['payment_id'] ?? '');
        $externalId = (string) ($data['external_id'] ?? $data['externalId'] ?? $payload['externalId'] ?? '');
        $amount = $data['amount'] ?? $payload['amount'] ?? null;
        $currency = $data['currency'] ?? $payload['currency'] ?? null;
        $channel = $data['channel'] ?? $payload['channel'] ?? null;
        $paidAt = $data['paid_at'] ?? $data['paidAt'] ?? $payload['paidAt'] ?? null;

        return [
            'status' => self::normalizeStatus($status),
            'reference' => $reference,
            'payment_id' => $paymentId !== '' ? $paymentId : null,
            'external_id' => $externalId !== '' ? $externalId : null,
            'amount' => is_numeric($amount) ? (int) $amount : null,
            'currency' => is_string($currency) ? strtoupper($currency) : null,
            'channel' => is_string($channel) ? $channel : null,
            'paid_at' => is_string($paidAt) ? $paidAt : null,
        ];
    }

    private static function normalizeStatus(string $status): string
    {
        return match ($status) {
            'success', 'successful', 'succeeded' => 'success',
            'failed', 'failure' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending',
            default => $status,
        };
    }
}
