<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MomoWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('momo/webhook', MomoWebhookController::class)->name('momo.webhook');
});
