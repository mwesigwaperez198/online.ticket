@extends('layouts.app')

@section('title', 'Confirm your Mobile Money payment — Novara Tickets')

@section('content')
<section class="min-h-[80vh] flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-md animate-fade-up">

        {{-- Phone icon with pulse ring --}}
        <div class="flex justify-center mb-8">
            <div class="relative">
                <div class="h-20 w-20 rounded-full bg-gradient-to-br from-neon/20 to-pulse/20 border border-neon/30 flex items-center justify-center pulse-ring">
                    <svg class="h-9 w-9 text-neon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="text-center mb-6">
            <h1 class="font-display text-2xl font-black text-slate-100">Check your phone</h1>
            <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                A Mobile Money request was sent to
                <span class="font-mono text-slate-200 font-medium">{{ $order->buyer_phone }}</span>.
                Enter your PIN to confirm.
            </p>
        </div>

        <div class="panel p-6 space-y-4">

            {{-- Order summary --}}
            <div class="space-y-2.5">
                @foreach([
                    ['Event', $order->listing->title],
                    ['Reference', $order->tx_ref],
                    ['MoMo number', $order->buyer_phone],
                ] as [$label, $value])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">{{ $label }}</span>
                        <span class="text-slate-200 font-mono text-xs">{{ $value }}</span>
                    </div>
                @endforeach
                <div class="flex items-center justify-between pt-2 border-t border-edge/60">
                    <span class="text-slate-500 text-sm">Amount</span>
                    <span class="font-display font-bold text-neon text-xl">@money($order->total_minor)</span>
                </div>
            </div>

            <div class="divider"></div>

            {{-- Steps --}}
            <div class="space-y-2.5">
                @foreach([
                    ['1', 'Your phone rings or a USSD prompt appears'],
                    ['2', 'Enter your Mobile Money PIN and approve'],
                    ['3', 'This page updates automatically once MTN confirms'],
                ] as [$n, $step])
                    <div class="flex items-start gap-3 text-sm text-slate-400">
                        <span class="h-5 w-5 rounded-full bg-neon/10 border border-neon/30 text-neon text-xs flex items-center justify-center shrink-0 font-bold mt-0.5">{{ $n }}</span>
                        {{ $step }}
                    </div>
                @endforeach
            </div>

            {{-- Live status --}}
            <div id="status-box" class="rounded-xl border border-edge bg-void/60 px-4 py-3 flex items-center gap-3 text-sm text-slate-400">
                <span class="h-2 w-2 rounded-full bg-amber animate-pulse shrink-0"></span>
                <span id="status-text">Waiting for your confirmation…</span>
            </div>

            <button id="check-btn" onclick="poll()"
                    class="btn-outline w-full rounded-xl py-2.5 text-sm">
                Check status now
            </button>

            <p class="text-center text-xs text-slate-600">
                Never approve a prompt you didn't start. Novara never asks for your PIN.
            </p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
const statusBox  = document.getElementById('status-box');
const statusText = document.getElementById('status-text');
const statusUrl  = @json(route('checkout.status', ['tx_ref' => $order->tx_ref]));
const orderUrl   = @json(route('buyer.orders.show', $order));

function render(msg, tone) {
    const map = {
        pending: 'border-edge bg-void/60 text-slate-400',
        paid:    'border-acid/40 bg-acid/8 text-acid',
        failed:  'border-red-500/40 bg-red-500/8 text-red-400',
    };
    statusBox.className = 'rounded-xl border px-4 py-3 flex items-center gap-3 text-sm ' + (map[tone] || map.pending);
    statusText.textContent = msg;
}

async function poll() {
    try {
        const res  = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();

        if (data.paid) {
            render('Payment confirmed — taking you to your tickets…', 'paid');
            setTimeout(() => window.location.href = orderUrl, 1200);
            return;
        }
        if (['failed','cancelled','expired'].includes(data.status)) {
            render('Payment did not go through. Please start checkout again.', 'failed');
            return;
        }
        render('Waiting for your Mobile Money confirmation…', 'pending');
    } catch {
        render('Could not reach the server. Retrying…', 'pending');
    }
}

poll();
const timer = setInterval(async () => {
    const res  = await fetch(statusUrl, { headers: { Accept: 'application/json' } }).catch(() => null);
    if (!res || !res.ok) return;
    const data = await res.json();
    if (data.paid) { clearInterval(timer); render('Payment confirmed — taking you to your tickets…', 'paid'); setTimeout(() => window.location.href = orderUrl, 1200); }
    else if (['failed','cancelled','expired'].includes(data.status)) { clearInterval(timer); render('Payment did not go through. Please start checkout again.', 'failed'); }
}, 4000);
</script>
@endpush
