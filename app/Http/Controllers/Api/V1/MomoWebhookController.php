<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MomoWebhookController extends Controller
{
    public function __invoke(Request $request, MomoGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        $raw = $request->getContent();

        if (! $gateway->verifyWebhookSignature($raw, $request->headers->all())) {
            Log::warning('momo webhook signature rejected', ['ip' => $request->ip()]);

            return response()->json(['status' => false, 'message' => 'invalid signature'], 401);
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return response()->json(['status' => false, 'message' => 'invalid json'], 422);
        }

        $data = (array) ($payload['data'] ?? []);

        if (($data['status'] ?? null) !== 'success') {
            Log::info('momo webhook ignored', ['status' => $data['status'] ?? null]);

            return response()->json(['status' => true, 'message' => 'ignored']);
        }

        $txRef = (string) ($data['reference'] ?? '');

        if ($txRef === '') {
            return response()->json(['status' => false, 'message' => 'missing reference'], 422);
        }

        $already = WebhookEvent::query()
            ->where('provider', $gateway->name())
            ->where('event_id', $txRef)
            ->exists();

        if ($already) {
            return response()->json(['status' => true, 'message' => 'duplicate ignored']);
        }

        DB::transaction(function () use ($txRef, $data, $payload, $gateway, $processor): void {
            WebhookEvent::query()->create([
                'provider' => $gateway->name(),
                'event_id' => $txRef,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            $order = Order::query()->where('tx_ref', $txRef)->first();

            if ($order === null) {
                Log::warning('momo webhook for unknown tx_ref', ['tx_ref' => $txRef]);

                return;
            }

            if ((int) ($data['amount'] ?? 0) !== $order->total_minor) {
                Log::warning('momo webhook amount mismatch', [
                    'tx_ref' => $txRef,
                    'expected' => $order->total_minor,
                    'received' => $data['amount'] ?? null,
                ]);

                return;
            }

            $processor->markPaid($order, $data, $gateway->name());
        });

        return response()->json(['status' => true, 'message' => 'ok']);
    }
}
