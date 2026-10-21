@extends('layouts.app')

@section('title', 'Mobile Money confirmation — Novara Tickets')

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <span class="pill pill-info">Demo Mobile Money</span>
        <h1 class="mt-4 font-display text-2xl font-black">Confirm payment</h1>

        <dl class="mt-6 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Reference</dt><dd class="font-mono text-slate-200">{{ $order->tx_ref }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Event</dt><dd class="text-slate-200">{{ $order->listing->title }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-display font-bold text-neon">@money($order->total_minor)</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">MoMo number</dt><dd class="text-slate-200">{{ $order->buyer_phone }}</dd></div>
        </dl>

        @if($demo)
            <form method="POST" action="{{ route('checkout.demo.approve', $order->tx_ref) }}" class="mt-7 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">MoMo PIN</label>
                    <input type="password" name="pin" value="1234" class="input-cyber text-sm">
                </div>
                <button class="btn-neon w-full rounded-xl px-4 py-3">Approve @money($order->total_minor)</button>
                <p class="text-center text-xs text-slate-500">Sandbox approval — issues real tickets against the local ledger.</p>
            </form>
        @else
            <p class="mt-7 text-sm text-slate-400">This order is waiting for the gateway to confirm the Mobile Money charge.</p>
            <a href="{{ route('checkout.return', ['reference' => $order->tx_ref]) }}" class="btn-ghost mt-4 block rounded-xl px-4 py-3 text-center">Check payment status</a>
        @endif
    </div>
</section>
@endsection
