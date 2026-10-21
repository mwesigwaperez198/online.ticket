@extends('layouts.app')

@section('title', 'Orders — Pulse Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Order control</h1>
    <p class="mt-1 text-sm text-slate-500">Inspect payments, issue refunds and audit the platform take.</p>

    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        @foreach(['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'expired' => 'Expired'] as $key => $label)
            <a href="{{ route('admin.orders.index', array_filter(['status' => $key])) }}"
               class="rounded-lg border px-3 py-1.5 text-xs uppercase tracking-wider transition {{ $status === $key ? 'border-neon/60 bg-neon/10 text-neon' : 'border-edge text-slate-400 hover:border-neon/40 hover:text-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </form>

    <div class="panel mt-4 overflow-x-auto">
        <table class="data-table w-full min-w-[920px]">
            <thead>
                <tr><th>Reference</th><th>Buyer</th><th>Listing</th><th>Total</th><th>Fee</th><th>Status</th><th>Date</th><th></th></tr>
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
                        <td class="text-slate-300">{{ $order->buyer_name }}<br><span class="text-xs text-slate-500">{{ $order->buyer_phone }}</span></td>
                        <td class="text-slate-200">{{ $order->listing->title }}</td>
                        <td class="text-slate-200">GHS {{ number_format($order->total_minor / 100, 2) }}</td>
                        <td class="text-pulse">GHS {{ number_format($order->commission_minor / 100, 2) }}</td>
                        <td><span class="pill {{ $pill }}">{{ $order->status }}</span></td>
                        <td class="text-xs text-slate-500">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="text-right">
                            @if($order->status === 'paid')
                                <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="reason" value="Admin refund">
                                    <button class="text-xs uppercase tracking-wider text-red-300 hover:underline">Refund</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-12 text-center text-sm text-slate-500">No orders match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-10">{{ $orders->links() }}</div>
</section>
@endsection
