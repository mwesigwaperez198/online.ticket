<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PesapalMomoGateway implements MomoGateway
{
    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $baseUrl = 'https://sandbox.pesapal.com',
        private readonly string $currency = 'UGX',
        private readonly string $callbackUrl = '',
    ) {}

    public function name(): string
    {
        return 'pesapal';
    }

    public function initialize(array $payload): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? $this->currency));
        $txRef = (string) $payload['tx_ref'];
        $amount = Money::toGatewayAmount((int) $payload['amount_minor'], $currency);

        $token = $this->requestToken();
        $notificationId = $this->registerIpn($token);

        $body = [
            'id' => $txRef,
            'currency' => $currency,
            'amount' => (string) $amount,
            'description' => 'Ticket purchase '.$txRef,
            'callback_url' => $payload['callback_url'] ?? $this->callbackUrl,
            'notification_id' => $notificationId,
            'type' => 'MERCHANT',
            'reference' => $txRef,
            'first_name' => (string) ($payload['name'] ?? 'Customer'),
            'last_name' => 'Customer',
            'email' => (string) ($payload['email'] ?? ''),
            'phone_number' => (string) ($payload['phone'] ?? ''),
            'country' => 'UG',
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl.'/v3/api/Transactions/SubmitOrderRequest', $body);

        if ($response->failed()) {
            throw new RuntimeException('Pesapal payment initialization failed: '.$response->status());
        }

        $json = $response->json();

        $reference = (string) ($json['order_reference'] ?? $json['reference'] ?? $txRef);
        $redirect = (string) ($json['redirect_url'] ?? $json['payment_url'] ?? route('checkout.prompt', ['tx_ref' => $txRef]));

        return [
            'authorization_url' => $redirect,
            'reference' => $reference,
            'raw' => $json,
        ];
    }

    public function verify(string $txRef): ?array
    {
        $token = $this->requestToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->baseUrl.'/v3/api/Transactions/GetTransactionStatus', [
                'reference' => $txRef,
            ]);

        if ($response->failed()) {
            return null;
        }

        $json = $response->json();

        $status = strtolower((string) ($json['status'] ?? $json['transaction_status'] ?? 'pending'));
        $amount = $json['amount'] ?? $json['total_amount'] ?? null;

        return [
            'status' => match ($status) {
                'success', 'successful', 'completed', 'paid' => 'success',
                'failed', 'cancelled', 'declined' => 'failed',
                'expired', 'timeout' => 'expired',
                default => 'pending',
            },
            'reference' => (string) ($json['reference'] ?? $txRef),
            'external_id' => (string) ($json['id'] ?? ''),
            'payer' => (string) ($json['phone_number'] ?? ''),
            'amount' => is_numeric($amount) ? Money::toMinor((float) $amount) : null,
            'currency' => strtoupper((string) ($json['currency'] ?? $this->currency)),
            'channel' => 'mobile_money',
            'paid_at' => now()->toIso8601String(),
        ];
    }

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool
    {
        $token = $this->requestToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl.'/v3/api/Transactions/Refund', [
                'reference' => $gatewayReference,
                'amount' => (string) Money::toGatewayAmount($amountMinor, $this->currency),
                'reason' => $reason ?? 'Refund requested',
            ]);

        return $response->successful();
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return true;
    }

    private function requestToken(): string
    {
        $response = Http::asForm()
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->post($this->baseUrl.'/v3/api/Auth/RequestToken', [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Pesapal token request failed: '.$response->status());
        }

        $json = $response->json();

        if (! isset($json['token'])) {
            throw new RuntimeException('Pesapal token missing from response.');
        }

        return (string) $json['token'];
    }

    private function registerIpn(string $token): string
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl.'/v3/api/URLSetup/RegisterIPN', [
                'url' => $this->callbackUrl,
                'ipn_notification_type' => 'GET',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Pesapal IPN registration failed: '.$response->status());
        }

        $json = $response->json();

        return (string) ($json['ipn_id'] ?? $json['id'] ?? '');
    }
}
