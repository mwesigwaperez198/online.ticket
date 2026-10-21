<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SettingsController extends Controller
{
    /**
     * The platform commission is always prompted for in the admin console.
     */
    public function updateCommission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $bps = (int) round(((float) $validated['commission_percent']) * 100);

        Setting::put('platform_commission_bps', (string) $bps, 'payments', 'integer');

        Audit::log('settings.commission_updated', null, [
            'bps' => $bps,
            'percent' => $validated['commission_percent'],
        ]);

        return back()->with('status', 'Platform commission set to '.$validated['commission_percent'].'% on new sales.');
    }

    /**
     * Settle an organizer wallet by Mobile Money transfer.
     */
    public function payout(Request $request, Organizer $organizer): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:bank,momo,manual'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $wallet = $organizer->wallet()->first();

        if ($wallet === null) {
            return back()->with('error', 'This organizer has no wallet yet.');
        }

        $amount = (int) round(((float) $validated['amount']) * 100);

        if ($amount > (int) $wallet->balance_minor) {
            return back()->with('error', 'Amount exceeds the available balance.');
        }

        DB::transaction(function () use ($organizer, $wallet, $amount, $validated, $request): void {
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $locked->decrement('balance_minor', $amount);

            WalletTransaction::query()->create([
                'wallet_id' => $locked->id,
                'organizer_id' => $organizer->id,
                'order_id' => null,
                'type' => 'payout',
                'direction' => 'debit',
                'amount_minor' => -$amount,
                'balance_after' => (int) $locked->fresh()->balance_minor,
                'memo' => 'Payout via '.$validated['method'],
                'created_by' => $request->user()->id,
            ]);

            Payout::query()->create([
                'organizer_id' => $organizer->id,
                'amount_minor' => $amount,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'status' => 'paid',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'paid_at' => now(),
            ]);
        });

        Audit::log('payout.settled', $organizer, ['amount_minor' => $amount]);

        return back()->with('status', 'Payout of '.Money::format($amount).' recorded.');
    }
}
