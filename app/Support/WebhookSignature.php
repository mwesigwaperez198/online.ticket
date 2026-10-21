<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Transport level verification of Mobile Money webhook signatures.
 *
 * The platform settles money from webhook callbacks, so a callback is only
 * trusted when it proves it came from the payment provider:
 *
 * - Paystack: `x-paystack-signature` = HMAC-SHA512(raw body, secret)
 * - Flutterwave: `verif-hash` = SHA512(raw body + secret)
 * - MTN MoMo: the result notification carries no signature at all, so it is
 *   authenticated by confirming the payment with MTN over the server to
 *   server status endpoint instead. The payment id in the notification has
 *   to match the one the platform stored when it asked MTN to collect.
 */
final class WebhookSignature
{
    public const PAYSTACK = 'paystack';

    public const FLUTTERWAVE = 'flutterwave';

    public const MTN = 'mtn';

    /**
     * The request attribute the middleware stores the verified provider in.
     */
    public const ATTRIBUTE = 'webhook.provider';

    /**
     * Set when the provider does not sign its notifications.
     */
    public const CONFIRMATION_ATTRIBUTE = 'webhook.confirmation';

    /**
     * Providers whose signatures the webhook endpoint accepts.
     *
     * @return array<int, string>
     */
    public static function allowedProviders(): array
    {
        $configured = (array) config('ticketing.momo.webhook_providers', [self::PAYSTACK]);
        $providers = [];

        foreach ($configured as $provider) {
            $provider = strtolower(trim((string) $provider));

            if (in_array($provider, [self::PAYSTACK, self::FLUTTERWAVE, self::MTN], true)) {
                $providers[] = $provider;
            }
        }

        return $providers !== [] ? array_values(array_unique($providers)) : [self::PAYSTACK];
    }

    /**
     * The shared secret used to verify a provider's callbacks.
     */
    public static function secretFor(string $provider): string
    {
        return match (strtolower($provider)) {
            self::PAYSTACK => trim((string) config('ticketing.paystack.secret_key', '')),
            self::FLUTTERWAVE => trim((string) config('ticketing.momo.flutterwave_secret', '')),
            self::MTN => trim((string) config('ticketing.momo.mtn.webhook_secret', '')),
            default => '',
        };
    }

    /**
     * Does the provider sign its notifications at all?
     */
    public static function requiresConfirmation(string $provider): bool
    {
        return strtolower($provider) === self::MTN;
    }

    /**
     * Does the request carry a valid signature for the given provider?
     */
    public static function matches(string $provider, Request $request, string $rawBody, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        return match (strtolower($provider)) {
            self::PAYSTACK => self::matchesPaystack($request, $rawBody, $secret),
            self::FLUTTERWAVE => self::matchesFlutterwave($request, $rawBody, $secret),
            self::MTN => self::matchesApiKey($request, $secret),
            default => false,
        };
    }

    private static function matchesPaystack(Request $request, string $rawBody, string $secret): bool
    {
        $signature = trim((string) $request->header('x-paystack-signature', ''));

        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    private static function matchesFlutterwave(Request $request, string $rawBody, string $secret): bool
    {
        $hash = trim((string) $request->header('verif-hash', ''));

        if ($hash === '') {
            return false;
        }

        return hash_equals(hash('sha512', $rawBody.$secret), $hash);
    }

    /**
     * Optional shared secret for providers that do not sign their payload.
     */
    private static function matchesApiKey(Request $request, string $secret): bool
    {
        $apiKey = trim((string) $request->header('x-api-key', ''));

        if ($apiKey === '') {
            return false;
        }

        return hash_equals($secret, $apiKey);
    }
}
