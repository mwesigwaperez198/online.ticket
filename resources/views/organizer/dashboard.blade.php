@extends('layouts.app')

@section('title', 'Organizer dashboard — Pulse Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="pill {{ $organizer && $organizer->isApproved() ? 'pill-live' : 'pill-pending' }}">
                {{ $organizer && $organizer->isApproved() ? 'Approved organizer' : 'Awaiting approval' }}
            </span>
            <h1 class="mt-3 font-display text-3xl font-black">{{ $organizer?->business_name ?? 'Organizer' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Sales, wallet balance, capacity and gate activity in one view.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('organizer.scanner') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">Open scanner</a>
            <a href="{{ route('organizer.listings.create') }}" class="btn-neon rounded-xl px-4 py-2.5 text-sm">New listing</a>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Tickets sold', number_format($metrics['tickets_sold']), 'text-neon'],
            ['Wallet balance', 'GHS '.number_format($metrics['balance_minor'] / 100, 2), 'text-acid'],
            ['Gross volume', 'GHS '.number_format($metrics['gross_minor'] / 100, 2), 'text-slate-100'],
            ['Platform fee', 'GHS '.number_format($metrics['commission_minor'] / 100, 2), 'text-pulse'],
        ] as [$label, $value, $tone])
            <div class="panel px-5 py-4">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-black {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Net earnings</p>
            <p class="mt-2 font-display text-xl font-bold text-slate-100">GHS {{ number_format($metrics['net_minor'] / 100, 2) }}</p>
        </div>
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Capacity remaining</p>
            <p class="mt-2 font-display text-xl font-bold text-slate-100">
                {{ number_format($metrics['capacity_remaining']) }}
                <span class="text-sm font-normal text-slate-500">of {{ number_format($metrics['capacity_total']) }}</span>
            </p>
        </div>
        <div class="panel px-5 py-4">
            <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Paid orders</p>
            <p class="mt-2 font-display text-xl font-bold text-slate-100">{{ number_format($metrics['orders']) }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
                <h2 class="font-display text-lg font-bold">Recent orders</h2>
                <a href="{{ route('organizer.listings.index') }}" class="text-xs uppercase tracking-wider text-neon hover:underline">All listings</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[560px]">
                    <thead><tr><th>Reference</th><th>Listing</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="font-mono text-xs text-slate-300">{{ $order->tx_ref }}</td>
                                <td class="text-slate-200">{{ $order->listing->title }}</td>
                                <td class="text-slate-200">GHS {{ number_format($order->total_minor / 100, 2) }}</td>
                                <td><span class="pill {{ $order->status === 'paid' ? 'pill-paid' : 'pill-pending' }}">{{ $order->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
                <h2 class="font-display text-lg font-bold">Check-in log</h2>
                <a href="{{ route('organizer.scanner') }}" class="text-xs uppercase tracking-wider text-neon hover:underline">Scan tickets</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[560px]">
                    <thead><tr><th>Ticket</th><th>Listing</th><th>Scanned at</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($checkIns as $ticket)
                            <tr>
                                <td class="font-mono text-xs text-slate-300">{{ $ticket->code }}</td>
                                <td class="text-slate-200">{{ $ticket->listing->title }}</td>
                                <td class="text-xs text-slate-500">{{ $ticket->used_at?->format('d M H:i') }}</td>
                                <td><span class="pill pill-paid">checked in</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">Nobody has checked in yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
