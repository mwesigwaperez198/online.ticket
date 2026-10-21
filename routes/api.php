<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MomoWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Stateless API surface.
 *
 * The Mobile Money webhook is the only public write endpoint. It is
 * protected by the signature middleware — a callback that is not signed by
 * the payment provider never reaches the settlement code — and by a burst
 * tolerant throttle so a provider retry storm cannot exhaust the app.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('momo/webhook', MomoWebhookController::class)
        ->middleware(['webhook.signature', 'throttle:webhook'])
        ->name('momo.webhook');
});
