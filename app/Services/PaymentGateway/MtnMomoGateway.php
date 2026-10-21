<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Money;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MTN MoMo Collections gateway.
 *
 * The buyer authorises the charge on their own phone: the platform asks MTN
 * to collect the money, MTN pushes a USSD prompt to the Mobile Money number
 * and the buyer confirms it with their PIN. There is no browser based
 * authorisation page, so the checkout sends the buyer to the "check your
 * phone" screen and waits for the result notification.
 *
 * Result notifications are not signed by MTN, so a notification is never
 * trusted on its own: the payment id has to match the one the platform
 * stored when it asked MTN to collect, and the charge is confirmed with a
 * server to server status lookup before any money moves.
 */
final class MtnMomoGateway implements MomoGateway
{
    public function __construct(
        private readonly string $primaryKey,
        private readonly string $secondaryKey = '',
        private readonly string $baseUrl = 'https://sandbox.momodeveloper.mtn.com',
        private readonly string $targetEnvironment = 'UgxAndMore',
        private readonly string $currency = 'UGX',
        private readonly string $payee = 'Novara',
        private readonly string $callbackUrl = '',
    ) {}

    public function name(): string
    {
        return 'mtn';
    }

    public function initialize(array $payload): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? $this->currency));
        $txRef = (string) $payload['tx_ref'];
        $amount = Money::toGatewayAmount((int) $payload['amount_minor'], $currency);
        $callbackUrl = $this->callbackUrl !== '' ? $this->callbackUrl : route('api.v1.momo.webhook');

        $body = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'externalId' => $txRef,
            'msisdn' => self::normaliseMsisdn((string) ($payload['phone'] ?? '')),
            'reason' => 'Novara ticket purchase '.$txRef,
            'payee' => $this->payee,
            'customerName' => (string) ($payload['name'] ?? 'Novara customer'),
            'callbackUrl' => $callbackUrl,
        ];

        $response = $this->client($this->primaryKey)
            ->withHeaders([
                'X-Reference' => $txRef,
                'X-Callback-Url' => $callbackUrl,
            ])
            ->post('/collection/v1_0/request-payment', $body);

        if ($response->status() === 401 || $response->status() === 403) {
            // The primary subscription key is not entitled to the collections
            // product; the secondary key belongs to that product.
            $response = $this->client($this->secondaryKey)
                ->withHeaders([
                    'X-Reference' => $txRef,
                    'X-Callback-Url' => $callbackUrl,
                ])
                ->post('/collection/v1_0/request-payment', $body);
        }

        if ($response->failed()) {
            throw new RuntimeException('MTN declined the payment request: '.$response->status());
        }

        $data = (array) $response->json();
        $paymentId = (string) ($data['paymentId'] ?? '');

        if ($paymentId === '') {
            throw new RuntimeException('MTN did not return a payment id.');
        }

        return [
            'authorization_url' => route('checkout.prompt', ['tx_ref' => $txRef]),
            'reference' => $paymentId,
            'raw' => $data,
        ];
    }

    /**
     * Confirm a charge with MTN and report what MTN says about it.
     *
     * @return array<string,mixed>|null
     */
    public function verify(string $txRef): ?array
    {
        $response = $this->client($this->primaryKey)
            ->get('/collection/v1_0/request-payment/'.rawurlencode($txRef));

        if ($response->status() === 401 || $response->status() === 403) {
            $response = $this->client($this->secondaryKey)
                ->get('/collection/v1_0/request-payment/'.rawurlencode($txRef));
        }

        if ($response->failed()) {
            return null;
        }

        $data = (array) $response->json();
        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        $amount = $data['amount'] ?? null;

        return [
            'status' => match ($status) {
                'SUCCESS' => 'success',
                'FAILED' => 'failed',
                'EXPIRED', 'TIMEOUT' => 'expired',
                default => strtolower($status),
            },
            'reference' => (string) ($data['paymentId'] ?? $txRef),
            'external_id' => (string) ($data['externalId'] ?? ''),
            'payer' => (string) ($data['payerNumber'] ?? ''),
            'amount' => is_numeric($amount) ? Money::toMinor((float) $amount) : null,
            'currency' => strtoupper((string) ($data['currency'] ?? $this->currency)),
            'channel' => 'mobile_money',
            'paid_at' => now()->toIso8601String(),
        ];
    }

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool
    {
        $amount = Money::toGatewayAmount($amountMinor, $this->currency);

        $body = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => strtoupper($this->currency),
            'externalId' => $gatewayReference,
            'paymentId' => $gatewayReference,
            'reason' => $reason ?? 'Novara refund',
        ];

        $response = $this->client($this->primaryKey)->post('/collection/v1_0/refund', $body);

        if ($response->status() === 401 || $response->status() === 403) {
            // The refund product can sit on the secondary subscription key.
            $response = $this->client($this->secondaryKey)->post('/collection/v1_0/refund', $body);
        }

        return $response->successful();
    }

    /**
     * MTN result notifications carry no signature. The notification is
     * authenticated by the server to server status lookup that the webhook
     * controller performs before an order is ever settled.
     *
     * @param  array<string,mixed>  $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return true;
    }

    /**
     * The MSISDN in the international format MTN expects, e.g. 256772123456.
     */
    public static function normaliseMsisdn(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '256')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '256'.substr($digits, 1);
        }

        return $digits;
    }

    private function client(string $subscriptionKey): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->targetEnvironment,
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 400, self::retryOnServerError(), throw: false);
    }

    /**
     * Only a connection failure or a server error is worth repeating. A
     * rejected subscription key has to fall back to the secondary key
     * straight away instead of being retried.
     */
    private static function retryOnServerError(): Closure
    {
        return static function (mixed $exception): bool {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            return $exception instanceof RequestException && $exception->response->serverError();
        };
    }
}
