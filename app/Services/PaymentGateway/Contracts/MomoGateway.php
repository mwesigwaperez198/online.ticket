<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway\Contracts;

interface MomoGateway
{
    /**
     * @param  array{tx_ref:string, amount_minor:int, currency:string, email:string, name:string|null, phone:string|null, callback_url:string, channels:array<int,string>, metadata:array<string,mixed>}  $payload
     * @return array{authorization_url:string, reference:string, raw:array<string,mixed>}
     */
    public function initialize(array $payload): array;

    /**
     * @return array<string,mixed>|null
     */
    public function verify(string $txRef): ?array;

    public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool;

    /**
     * @param  array<string,mixed>  $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    public function name(): string;
}
