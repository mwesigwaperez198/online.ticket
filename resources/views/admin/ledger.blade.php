@extends('layouts.app')

@section('title', 'Platform ledger — Novara Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Platform ledger</h1>
    <p class="mt-1 text-sm text-slate-500">Every credit and debit that touched a wallet or the platform fee account.</p>

    <div class="mt-8 grid gap-4 sm:grid-cols-4">
        @foreach([
            ['Gross settled', \App\Support\Money::format($totals['gross']), 'text-neon'],
            ['Wallet credits', \App\Support\Money::format($totals['credits']), 'text-acid'],
            ['Wallet debits', \App\Support\Money::format(abs($totals['debits'])), 'text-red-300'],
            ['Commission booked', \App\Support\Money::format($totals['platform_fees']), 'text-pulse'],
        ] as [$label, $value, $tone])
            <div class="panel px-5 py-4">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-xl font-black {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        @foreach(['' => 'All', 'sale' => 'Sales', 'fee' => 'Commission', 'refund' => 'Refunds', 'payout' => 'Payouts'] as $key => $label)
            <a href="{{ route('admin.ledger', array_filter(['type' => $key])) }}"
               class="rounded-lg border px-3 py-1.5 text-xs uppercase tracking-wider transition {{ $type === $key ? 'border-neon/60 bg-neon/10 text-neon' : 'border-edge text-slate-400 hover:border-neon/40 hover:text-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </form>

    <div class="panel mt-4 overflow-x-auto">
        <table class="data-table w-full min-w-[820px]">
            <thead>
                <tr><th>Type</th><th>Organizer</th><th>Order</th><th>Memo</th><th>Direction</th><th>Amount</th><th>Balance</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td><span class="pill {{ match ($tx->type) { 'sale' => 'pill-paid', 'fee' => 'pill-pending', 'refund' => 'pill-closed', default => 'pill-info' } }}">{{ $tx->type }}</span></td>
                        <td class="text-slate-300">{{ $tx->organizer?->business_name ?? 'Platform' }}</td>
                        <td class="font-mono text-xs text-slate-400">{{ $tx->order?->tx_ref ?? '—' }}</td>
                        <td class="text-slate-300">{{ $tx->memo }}</td>
                        <td class="text-xs text-slate-500">{{ $tx->direction }}</td>
                        <td class="{{ $tx->amount_minor >= 0 ? 'text-acid' : 'text-red-300' }}">@money(abs($tx->amount_minor))</td>
                        <td class="text-slate-400">@money(($tx->balance_after ?? 0))</td>
                        <td class="text-xs text-slate-500">{{ $tx->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-sm text-slate-500">No ledger entries recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-10">{{ $transactions->links() }}</div>
</section>
@endsection
