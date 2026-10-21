<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PayoutController extends Controller
{
    use OrganizerScope;

    public function index(): View
    {
        $organizer = $this->organizer();

        return view('organizer.payouts', [
            'organizer' => $organizer,
            'wallet' => $organizer?->wallet()->first(),
            'transactions' => WalletTransaction::query()
                ->where('organizer_id', $organizer?->id ?? 0)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:bank,momo,manual'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $organizer = $this->organizer();

        if ($organizer === null) {
            return back()->with('error', 'Organizer profile missing.');
        }

        $wallet = $organizer->wallet()->first();
        $available = (int) ($wallet->balance_minor ?? 0);
        $requested = (int) round(((float) $validated['amount']) * 100);

        if ($requested <= 0) {
            return back()->with('error', 'Enter a valid amount.');
        }

        if ($requested > $available) {
            return back()->with('error', 'You cannot request more than your available balance of GHS '.number_format($available / 100, 2).'.');
        }

        Audit::log('payout.requested', $organizer, [
            'amount_minor' => $requested,
            'method' => $validated['method'],
        ]);

        return back()->with('status', 'Payout request submitted. The platform will confirm your Mobile Money transfer.');
    }
}
