@extends('layouts.app')

@section('title', 'Wallet & payouts — Novara Tickets')

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Wallet &amp; payouts</h1>
    <p class="mt-1 text-sm text-slate-500">Your Mobile Money balance and full ledger movement.</p>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Available balance</p>
            <p class="mt-2 font-display text-2xl font-black text-acid">@money(($wallet?->balance_minor ?? 0))</p>
        </div>
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Lifetime earned</p>
            <p class="mt-2 font-display text-2xl font-black text-neon">@money(($wallet?->lifetime_earned_minor ?? 0))</p>
        </div>
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Refunded</p>
            <p class="mt-2 font-display text-2xl font-black text-pulse">@money(($wallet?->lifetime_refunded_minor ?? 0))</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="panel overflow-hidden">
            <h2 class="border-b border-edge/70 px-5 py-4 font-display text-lg font-bold">Ledger</h2>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[560px]">
                    <thead><tr><th>Type</th><th>Memo</th><th>Direction</th><th>Amount</th><th>Balance</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($transactions as $tx)
                            <tr>
                                <td><span class="pill {{ $tx->type === 'sale' ? 'pill-paid' : ($tx->type === 'refund' ? 'pill-closed' : 'pill-info') }}">{{ $tx->type }}</span></td>
                                <td class="text-slate-300">{{ $tx->memo }}</td>
                                <td class="text-xs text-slate-500">{{ $tx->direction }}</td>
                                <td class="{{ $tx->amount_minor >= 0 ? 'text-acid' : 'text-red-300' }}">@money(abs($tx->amount_minor))</td>
                                <td class="text-slate-400">@money(($tx->balance_after ?? 0))</td>
                                <td class="text-xs text-slate-500">{{ $tx->created_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-10 text-center text-sm text-slate-500">No ledger entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel p-6">
            <h2 class="font-display text-lg font-bold">Request a payout</h2>
            <p class="mt-1 text-xs text-slate-500">
                Transfers are made to
                <span class="text-slate-300">{{ $organizer?->payout_momo_number ?? 'your registered MoMo number' }}</span>
                @if($organizer?->payout_provider)({{ strtoupper($organizer->payout_provider) }})@endif
            </p>

            <form method="POST" action="{{ route('organizer.payouts.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Amount (UGX)</label>
                    <input type="number" step="0.01" min="1" max="{{ number_format(($wallet?->balance_minor ?? 0) / 100, 2) }}" name="amount" required class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Method</label>
                    <select name="method" class="input-cyber text-sm">
                        <option value="momo">Mobile Money</option>
                        <option value="bank">Bank transfer</option>
                        <option value="manual">Manual settlement</option>
                    </select>
                </div>
                <input type="text" name="notes" placeholder="Notes (optional)" class="input-cyber text-sm">
                <button class="btn-neon w-full rounded-xl px-4 py-3">Submit payout request</button>
            </form>
        </div>
    </div>
</section>
@endsection
