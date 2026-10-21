<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\WebhookSignature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook callbacks that are not proven to come from the payment
 * provider.
 *
 * The middleware verifies the signature against the raw request body, so a
 * replayed or forged body can never reach the settlement code. Providers
 * whose notifications carry no signature are passed through in confirmation
 * mode: the controller then proves the payment with the provider over the
 * server to server status endpoint before anything is settled.
 */
final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string ...$providers): Response
    {
        $allowed = $providers !== [] ? $providers : WebhookSignature::allowedProviders();
        $raw = $request->getContent();
        $configured = false;

        foreach ($allowed as $provider) {
            if (WebhookSignature::requiresConfirmation($provider)) {
                $secret = WebhookSignature::secretFor($provider);

                if ($secret !== '' && ! WebhookSignature::matches($provider, $request, $raw, $secret)) {
                    continue;
                }

                $configured = true;

                $request->attributes->set(WebhookSignature::ATTRIBUTE, $provider);
                $request->attributes->set(WebhookSignature::CONFIRMATION_ATTRIBUTE, true);

                return $next($request);
            }

            $secret = WebhookSignature::secretFor($provider);

            if ($secret === '') {
                continue;
            }

            $configured = true;

            if (! WebhookSignature::matches($provider, $request, $raw, $secret)) {
                continue;
            }

            $request->attributes->set(WebhookSignature::ATTRIBUTE, $provider);

            return $next($request);
        }

        if (! $configured) {
            Log::critical('momo webhook received but no provider secret is configured', [
                'ip' => $request->ip(),
                'providers' => $allowed,
            ]);

            return $this->deny('webhook verification is not configured', 503);
        }

        Log::warning('momo webhook signature rejected', [
            'ip' => $request->ip(),
            'providers' => $allowed,
        ]);

        return $this->deny('invalid signature', 401);
    }

    private function deny(string $message, int $status): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message], $status);
    }
}
