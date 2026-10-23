@extends('layouts.app')

@section('title', 'Order '.$order->tx_ref.' — Novara Tickets')

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="pill pill-info">{{ $order->tx_ref }}</span>
            <h1 class="mt-3 font-display text-3xl font-black">{{ $order->listing->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $order->listing->starts_at->format('D d M Y • H:i') }} • {{ $order->listing->venue }}</p>
        </div>
        <span class="pill {{ match ($order->status) { 'paid' => 'pill-paid', 'pending' => 'pill-pending', default => 'pill-closed' } }}">{{ $order->status }}</span>
    </div>

    @if($order->isPaid())
        <a href="{{ route('buyer.orders.receipt', $order) }}" class="btn-outline mt-5 inline-flex rounded-xl px-4 py-2.5 text-sm">Download receipt</a>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="panel overflow-x-auto">
                <table class="data-table w-full min-w-[560px]">
                    <thead>
                        <tr><th>Item</th><th>Unit</th><th>Qty</th><th>Line total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td class="text-slate-200">{{ $item->label() }}</td>
                                <td class="text-slate-400">@money($item->unit_price_minor)</td>
                                <td class="text-slate-400">{{ $item->quantity }}</td>
                                <td class="text-slate-200">@money($item->line_total_minor)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($order->tickets->isNotEmpty())
                <div class="panel p-5">
                    <h2 class="font-display text-lg font-bold">Tickets</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach($order->tickets as $ticket)
                            <div class="rounded-xl border border-edge/80 bg-void/50 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs text-slate-300">{{ $ticket->code }}</span>
                                    <span class="pill {{ $ticket->status === 'issued' ? 'pill-paid' : 'pill-closed' }}">{{ $ticket->status }}</span>
                                </div>
                                <div class="mt-3 flex justify-center rounded-lg bg-white p-2">
                                    {!! \App\Support\QrCode::svg($ticket->qr_code_hash, 140) !!}
                                </div>
                                <p class="mt-2 text-center text-[.65rem] uppercase tracking-wider text-slate-500">{{ $ticket->holder_name }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-6">
            <div class="panel p-5">
                <h2 class="font-display text-lg font-bold">Payment summary</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="text-slate-200">@money($order->subtotal_minor)</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Platform fee ({{ \App\Support\Money::percent($order->commission_bps_applied) }})</dt><dd class="text-slate-200">@money($order->commission_minor)</dd></div>
                    <div class="divider-glow my-2"></div>
                    <div class="flex justify-between text-base"><dt class="font-medium text-slate-300">Total paid</dt><dd class="font-display text-xl font-bold text-neon">@money($order->total_minor)</dd></div>
                    <div class="flex justify-between text-xs"><dt class="text-slate-500">Mobile Money</dt><dd class="text-slate-300">{{ $order->momo_provider }} • {{ $order->buyer_phone }}</dd></div>
                    <div class="flex justify-between text-xs"><dt class="text-slate-500">Gateway</dt><dd class="text-slate-300">{{ $order->gateway }} @if($order->gateway_reference)• {{ $order->gateway_reference }}@endif</dd></div>
                </dl>
            </div>

            @if($order->isPaid() && $order->tickets->where('status', 'issued')->isNotEmpty())
                <div class="panel p-5">
                    <h2 class="font-display text-lg font-bold">Request a refund</h2>
                    <p class="mt-1 text-xs text-slate-500">Available within {{ $order->listing->refund_window_hours }} hours of purchase. Commission is reversed proportionally.</p>

                    <form method="POST" action="{{ route('buyer.orders.refund', $order) }}" class="mt-4 space-y-3">
                        @csrf
                        <div class="space-y-2">
                            @foreach($order->tickets->where('status', 'issued') as $ticket)
                                <label class="flex items-center gap-2 text-sm text-slate-300">
                                    <input type="checkbox" name="ticket_ids[]" value="{{ $ticket->id }}" class="rounded border-edge bg-void">
                                    <span class="font-mono text-xs">{{ $ticket->code }}</span>
                                </label>
                            @endforeach
                        </div>
                        <input type="text" name="reason" placeholder="Reason (optional)" class="input-cyber text-sm">
                        <button class="btn-ghost w-full rounded-xl px-4 py-2.5 text-sm">Refund selected tickets</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</section>
@endsection
