<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Order;
use App\Services\PaymentGateway\Contracts\MomoGateway;

/**
 * Gateway double that captures the platform state at the exact moment the
 * Mobile Money provider is contacted, so a test can prove the order row was
 * committed before the payment request left the application.
 */
final class RecordingMomoGateway implements MomoGateway
{
    public ?string $authorizationUrl = null;

    /**
     * @var array<string,mixed>|null
     */
    public ?array $payload = null;

    public bool $orderExistedAtInitialize = false;

    public ?string $orderStatusAtInitialize = null;

    public ?int $orderTotalAtInitialize = null;

    /**
     * @var array<string,int>
     */
    private array $amounts = [];

    /**
     * @var array<string,bool>
     */
    private array $approved = [];

    public function name(): string
    {
        return 'recording';
    }

    public function initialize(array $payload): array
    {
        $this->payload = $payload;

        $order = Order::query()->where('tx_ref', $payload['tx_ref'])->first();

        $this->orderExistedAtInitialize = $order !== null;
        $this->orderStatusAtInitialize = $order?->status;
        $this->orderTotalAtInitialize = $order?->total_minor;

        $this->amounts[$payload['tx_ref']] = (int) $payload['amount_minor'];
        $this->authorizationUrl = 'https://momo.test/authorize/'.$payload['tx_ref'];

        return [
            'authorization_url' => $this->authorizationUrl,
            'reference' => $payload['tx_ref'],
            'raw' => ['status' => 'initialize'],
        ];
    }

    public function verify(string $txRef): ?array
    {
        if (! ($this->approved[$txRef] ?? false)) {
            return null;
        }

        return [
            'status' => 'success',
            'reference' => $txRef,
            'amount' => $this->amounts[$txRef] ?? 0,
            'currency' => 'UGX',
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

    /**
     * Simulate the customer approving the USSD prompt on their phone.
     */
    public function approve(string $txRef): void
    {
        $this->approved[$txRef] = true;
    }
}
