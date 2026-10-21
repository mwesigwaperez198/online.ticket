<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HoldExpiryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Release expired ticket holds. Render free instances have no cron,
     * so the sweep runs opportunistically on inbound requests.
     */
    protected function sweepExpiredHolds(HoldExpiryService $sweep): void
    {
        if (Cache::get('ticketing:sweep:lock') !== null) {
            return;
        }

        Cache::put('ticketing:sweep:lock', 1, 30);

        try {
            $sweep->sweep();
        } finally {
            Cache::forget('ticketing:sweep:lock');
        }
    }
}
