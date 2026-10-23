<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\WebhookSettlement;
use App\Support\WebhookPayload;
use App\Support\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acknowledges Mobile Money callbacks.
 *
 * The endpoint verifies the callback, stores the receipt and answers 200;
 * the provider confirmation lookup and the ledger write are deferred until
 * after the response has been flushed, because Render free instances cut
 * requests at 50 seconds. The settlement itself lives in
 * {@see WebhookSettlement}.
 */
final class MomoWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSettlement $settlement): JsonResponse
    {
        $provider = $request->attributes->get(WebhookSignature::ATTRIBUTE);

        // The signature middleware owns verification. A request that did not
        // pass it never reaches the settlement code.
        if (! is_string($provider) || $provider === '') {
            Log::warning('momo webhook reached the controller without a verified signature', ['ip' => $request->ip()]);

            return response()->json(['status' => false, 'message' => 'invalid signature'], 401);
        }

        $confirmation = (bool) $request->attributes->get(WebhookSignature::CONFIRMATION_ATTRIBUTE);

        $raw = $request->getContent();

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['status' => false, 'message' => 'invalid json'], 422);
        }

        if (! is_array($payload)) {
            return response()->json(['status' => false, 'message' => 'invalid json'], 422);
        }

        $data = WebhookPayload::normalize($payload);

        if (! $confirmation && ! in_array($data['status'], ['success', 'failed', 'cancelled', 'expired'], true)) {
            Log::info('momo webhook ignored', ['status' => $data['status'], 'provider' => $provider]);

            return response()->json(['status' => true, 'message' => 'ignored']);
        }

        $txRef = $confirmation
            ? (string) ($data['external_id'] ?? $data['reference'])
            : $data['reference'];

        if ($txRef === '') {
            return response()->json(['status' => false, 'message' => 'missing reference'], 422);
        }

        $recorded = DB::transaction(static function () use ($txRef, $payload, $provider): int {
            /*
             * The unique index on (provider, event_id) is the single source of
             * truth for idempotency: a replayed callback loses the insert race.
             *
             * `processed_at` stays null until a settlement finishes, which is
             * what lets an interrupted settlement be driven again from this
             * row without any local state.
             */
            return WebhookEvent::query()->insertOrIgnore([
                'provider' => $provider,
                'event_id' => $txRef,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'processed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $event = WebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $txRef)
            ->first();

        if ($event === null) {
            Log::error('momo webhook receipt could not be stored', ['tx_ref' => $txRef, 'provider' => $provider]);

            return response()->json(['status' => false, 'message' => 'receipt not stored'], 500);
        }

        if ($recorded === 0 && $event->processed_at !== null) {
            return response()->json(['status' => true, 'message' => 'duplicate ignored']);
        }

        /*
         * The gateway gets its answer now. Settlement runs in the
         * terminating phase, after the response has been flushed, so the
         * provider status lookup never counts against the gateway's or
         * Render's request window.
         */
        app()->terminating(static function () use ($settlement, $event): void {
            $settlement->settle($event);
        });

        return response()->json(['status' => true, 'message' => 'ok']);
    }
}
