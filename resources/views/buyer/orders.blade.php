@extends('layouts.app')

@section('title', 'My orders — Novara Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Order history</h1>
    <p class="mt-1 text-sm text-slate-500">Every Mobile Money transaction on your account.</p>

    <div class="panel mt-8 overflow-x-auto">
        <table class="data-table w-full min-w-[720px]">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Listing</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $pill = match ($order->status) {
                            'paid' => 'pill-paid',
                            'pending' => 'pill-pending',
                            'refunded', 'partially_refunded' => 'pill-info',
                            'expired', 'failed', 'cancelled' => 'pill-closed',
                            default => 'pill-info',
                        };
                    @endphp
                    <tr>
                        <td class="font-mono text-xs text-slate-300">{{ $order->tx_ref }}</td>
                        <td class="text-slate-200">{{ $order->listing->title }}</td>
                        <td class="text-slate-400">{{ $order->quantity }}</td>
                        <td class="text-slate-200">@money($order->total_minor)</td>
                        <td><span class="pill {{ $pill }}">{{ $order->status }}</span></td>
                        <td class="text-xs text-slate-500">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="text-right">
                            <a href="{{ route('buyer.orders.show', $order) }}" class="text-xs uppercase tracking-wider text-neon hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-sm text-slate-500">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-10">{{ $orders->links() }}</div>
</section>
@endsection
