@extends('layouts.app')

@section('title', 'Authorise your Mobile Money payment — Novara Tickets')

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        @include('partials.momo-provider-badge', ['providerKey' => $providerKey, 'label' => $provider])
        <h1 class="mt-4 font-display text-2xl font-black">Check your phone</h1>
        <p class="mt-2 text-sm text-slate-400">
            We have sent a Mobile Money request to <span class="font-mono text-slate-200">{{ $order->buyer_phone }}</span>.
            Open the MTN prompt on your phone, enter your PIN and confirm the payment.
        </p>

        <dl class="mt-6 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Reference</dt><dd class="font-mono text-slate-200">{{ $order->tx_ref }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Event</dt><dd class="text-slate-200">{{ $order->listing->title }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-display font-bold text-neon">@money($order->total_minor)</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">MoMo number</dt><dd class="text-slate-200">{{ $order->buyer_phone }}</dd></div>
        </dl>

        <ol class="mt-6 space-y-2 text-sm text-slate-400">
            <li>1. Your phone rings (or a USSD prompt appears).</li>
            <li>2. Enter your Mobile Money PIN and approve <span class="text-neon">@money($order->total_minor)</span>.</li>
            <li>3. This page updates itself as soon as MTN confirms the charge.</li>
        </ol>

        <div id="status-box" class="mt-6 rounded-xl border border-slate-700/60 bg-slate-900/60 px-4 py-3 text-sm text-slate-300">
            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-amber"></span>
            <span id="status-text" class="ml-2">Waiting for your Mobile Money confirmation…</span>
        </div>

        <a href="{{ route('checkout.status', ['tx_ref' => $order->tx_ref]) }}" id="check-link" class="btn-ghost mt-4 block rounded-xl px-4 py-3 text-center">Check payment status</a>

        <p class="mt-4 text-center text-xs text-slate-500">
            Never approve a Mobile Money prompt you did not start. Novara never asks for your PIN.
        </p>
    </div>
</section>
@endsection

@push('scripts')
<script>
    const statusBox = document.getElementById('status-box');
    const statusText = document.getElementById('status-text');
    const checkLink = document.getElementById('check-link');
    const statusUrl = @json(route('checkout.status', ['tx_ref' => $order->tx_ref]));
    const orderUrl = @json(route('buyer.orders.show', $order));

    function render(message, tone) {
        const tones = {
            pending: ['border-slate-700/60', 'bg-slate-900/60', 'text-slate-300'],
            paid: ['border-acid/60', 'bg-acid/10', 'text-acid'],
            failed: ['border-red-500/60', 'bg-red-500/10', 'text-red-300'],
        };
        const [border, bg, text] = tones[tone] || tones.pending;
        statusBox.className = 'mt-6 rounded-xl border px-4 py-3 text-sm ' + border + ' ' + bg + ' ' + text;
        statusText.className = 'ml-2';
        statusText.textContent = message;
    }

    async function poll() {
        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.paid) {
                render('Payment confirmed. Taking you to your tickets…', 'paid');
                window.location.href = orderUrl;
                return;
            }

            if (data.status === 'failed' || data.status === 'cancelled' || data.status === 'expired') {
                render('This payment did not go through. Please start the checkout again.', 'failed');
                checkLink.classList.add('hidden');
                return;
            }

            render('Waiting for your Mobile Money confirmation…', 'pending');
        } catch (e) {
            render('We could not reach the server. We will keep trying.', 'pending');
        }
    }

    checkLink.addEventListener('click', (event) => {
        event.preventDefault();
        poll();
    });

    poll();
    window.setInterval(poll, 4000);
</script>
@endpush
