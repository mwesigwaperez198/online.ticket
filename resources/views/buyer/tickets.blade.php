@extends('layouts.app')

@section('title', 'My tickets — Novara Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-black">My tickets</h1>
            <p class="mt-1 text-sm text-slate-500">Single-use QR passes issued against your Mobile Money payments.</p>
        </div>
        <a href="{{ route('buyer.orders') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">Order history</a>
    </div>

    @if(auth()->user() !== null && ! auth()->user()->isOrganizer() && ! auth()->user()->isAdmin())
        <div class="panel mt-6 flex flex-wrap items-center justify-between gap-4 border-neon/40 p-5">
            <div>
                <p class="font-display text-lg font-bold text-neon">Your account can sell, too</p>
                <p class="mt-1 text-sm text-slate-400">Open a seller workspace with your same sign-in and publish events, services or gate passes.</p>
            </div>
            <a href="{{ route('seller.onboarding') }}" class="btn-neon rounded-xl px-5 py-2.5 text-sm">Start selling</a>
        </div>
    @endif

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($tickets as $ticket)
            @php
                $statusPill = match ($ticket->status) {
                    'used' => 'pill-closed',
                    'refunded' => 'pill-closed',
                    'void' => 'pill-closed',
                    default => 'pill-paid',
                };
            @endphp
            <article class="panel panel-hover flex flex-col overflow-hidden p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-lg font-bold leading-tight">{{ $ticket->listing->title }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $ticket->listing->starts_at->format('D d M Y • H:i') }}</p>
                        <p class="text-xs text-slate-500">{{ $ticket->listing->venue }}</p>
                    </div>
                    <span class="pill {{ $statusPill }}">{{ $ticket->status }}</span>
                </div>

                <div class="mt-4 flex items-center justify-center rounded-xl bg-white p-3">
                    {!! \App\Support\QrCode::svg($ticket->qr_code_hash, 180) !!}
                </div>

                <dl class="mt-4 space-y-1 text-xs text-slate-400">
                    <div class="flex justify-between"><dt>Ticket code</dt><dd class="font-mono text-slate-200">{{ $ticket->code }}</dd></div>
                    <div class="flex justify-between"><dt>Holder</dt><dd class="text-slate-200">{{ $ticket->holder_name }}</dd></div>
                    <div class="flex justify-between"><dt>Order</dt><dd class="text-slate-200">{{ $ticket->order->tx_ref }}</dd></div>
                </dl>

                <a href="{{ route('buyer.orders.show', $ticket->order_id) }}" class="btn-ghost mt-4 rounded-xl px-4 py-2 text-center text-sm">View order</a>
            </article>
        @empty
            <div class="col-span-full panel p-12 text-center">
                <p class="font-display text-xl font-bold">No tickets yet</p>
                <p class="mt-2 text-sm text-slate-500">Your Mobile Money payment will appear here the moment it is confirmed.</p>
                <a href="{{ route('home') }}" class="btn-neon mt-6 inline-block rounded-xl px-6 py-3">Browse events</a>
            </div>
        @endforelse
    </div>

    <div class="mt-10">{{ $tickets->links() }}</div>
</section>
@endsection
