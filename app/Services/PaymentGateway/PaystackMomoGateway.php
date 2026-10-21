<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class PaystackMomoGateway implements MomoGateway
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.paystack.co',
        private readonly string $currency = 'UGX',
    ) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function initialize(array $payload): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? $this->currency));

        $body = [
            'email' => $payload['email'],
            'amount' => Money::toGatewayAmount((int) $payload['amount_minor'], $currency),
            'currency' => $currency,
            'reference' => $payload['tx_ref'],
            'callback_url' => $payload['callback_url'],
            'metadata' => $payload['metadata'] ?? [],
        ];

        if (($payload['channels'] ?? []) !== []) {
            $body['channels'] = $payload['channels'];
        }

        if (! empty($payload['phone'])) {
            $body['customer'] = array_filter([
                'phone' => $payload['phone'],
                'name' => $payload['name'] ?? null,
            ]);
        }

        $response = $this->client()->post('/transaction/initialize', $body);

        $response->throw();

        $data = (array) $response->json('data');

        return [
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'reference' => (string) ($data['reference'] ?? $payload['tx_ref']),
            'raw' => $data,
        ];
    }

    public function verify(string $txRef): ?array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($txRef));

        if ($response->failed()) {
            return null;
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            return null;
        }

        $data['amount'] = Money::fromGatewayAmount((int) ($data['amount'] ?? 0), $this->currency);
        $data['currency'] = strtoupper((string) ($data['currency'] ?? $this->currency));

        return $data;
    }

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool
    {
        $response = $this->client()->post('/refund', [
            'reference' => $gatewayReference,
            'amount' => Money::toGatewayAmount($amountMinor, $this->currency),
            'reason' => $reason ?? 'Buyer requested refund',
        ]);

        return $response->successful();
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signature = $this->header($headers, 'x-paystack-signature');

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $this->secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string,mixed>  $headers
     */
    private function header(array $headers, string $key): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== $key) {
                continue;
            }

            if (is_array($value)) {
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return (string) $value;
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
