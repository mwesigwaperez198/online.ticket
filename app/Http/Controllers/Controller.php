<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HoldExpiryService;
use App\Services\WebhookSettlement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Release expired ticket holds and finish webhook settlements that were
     * interrupted. Render free instances have no cron and no queue worker,
     * so both sweeps run opportunistically on inbound requests.
     */
    protected function sweepExpiredHolds(HoldExpiryService $sweep, WebhookSettlement $settlement): void
    {
        if (Cache::get('ticketing:sweep:lock') !== null) {
            return;
        }

        Cache::put('ticketing:sweep:lock', 1, 30);

        try {
            $sweep->sweep();
            $settlement->retryUnsettled();
        } finally {
            Cache::forget('ticketing:sweep:lock');
        }
    }
}
