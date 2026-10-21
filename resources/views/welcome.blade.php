@extends('layouts.app')

@php
    $platformFeeLabel = \App\Support\Money::percent((int) setting('platform_commission_bps', 2000));
@endphp

@section('title', 'Novara Tickets — Mobile Money events & services in Uganda')
@section('meta_description', 'Novara Tickets buys and sells event tickets, services and gate passes across Uganda. Pay instantly with MTN Mobile Money or Airtel Money. Everything by Novara.')

@section('content')
<section class="relative overflow-hidden border-b border-edge/70">
    <div class="absolute inset-0 grid-lines opacity-70"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
        <div class="max-w-3xl">
            <span class="pill pill-info">MTN Mobile Money · Airtel Money · Uganda</span>
            <h1 class="mt-6 font-display text-4xl font-black leading-[1.05] tracking-tight sm:text-6xl">
                Own the night.<br>
                <span class="neon-text">Sell the tickets.</span>
            </h1>
            <p class="mt-6 max-w-2xl text-base text-slate-400 sm:text-lg">
                Live events, service bookings and gate passes across Uganda — paid instantly with Mobile Money.
                {{ config('ticketing.brand.tagline', 'Everything by Novara.') }}
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="#events" class="btn-neon rounded-xl px-6 py-3">Browse live events</a>
                <a href="{{ route('register') }}?organizer=1" class="btn-ghost rounded-xl px-6 py-3">Sell tickets</a>
            </div>

            <dl class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach([
                    ['Live listings', number_format($listings->total())],
                    ['Mobile money', 'MTN / Airtel'],
                    ['Platform fee', $platformFeeLabel],
                    ['Instant tickets', 'QR + wallet'],
                ] as [$label, $value])
                    <div class="panel px-4 py-3">
                        <dt class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 font-display text-lg font-bold text-slate-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="panel grid gap-6 p-6 md:grid-cols-3">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500">Platform fee</p>
            <p class="mt-1 font-display text-4xl font-black text-acid">{{ $platformFeeLabel }}</p>
            <p class="mt-2 text-sm text-slate-400">
                A single platform fee is taken from every ticket sold on the platform. The admin approves the final
                rate for every organiser before their first payout, and the fee is itemised on every order.
            </p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500">You receive</p>
            <p class="mt-1 font-display text-4xl font-black text-neon">{{ \App\Support\Money::percent(10000 - (int) setting('platform_commission_bps', 2000)) }}</p>
            <p class="mt-2 text-sm text-slate-400">
                Settled straight into your organizer wallet and paid out to the Mobile Money number you submit for
                approval inside your workspace.
            </p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500">Payment</p>
            <p class="mt-1 font-display text-4xl font-black text-pulse">MoMo</p>
            <p class="mt-2 text-sm text-slate-400">
                Buyers approve a USSD prompt on their own phone with MTN Mobile Money or Airtel Money. Tickets are
                issued the moment the payment lands.
            </p>
        </div>
    </div>
</section>

<section id="events" class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-display text-2xl font-bold sm:text-3xl">Tonight &amp; upcoming</h2>
            <p class="mt-1 text-sm text-slate-500">Pick a tier, pay with Mobile Money, get your QR instantly.</p>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            @foreach(['all' => 'All', 'event' => 'Events', 'service' => 'Services'] as $key => $label)
                <a href="{{ route('home', array_filter(['type' => $key === 'all' ? null : $key, 'q' => $search])) }}"
                   class="rounded-lg border px-3 py-1.5 text-xs uppercase tracking-wider transition {{ ($type === $key || ($type === '' && $key === 'all')) ? 'border-neon/60 bg-neon/10 text-neon' : 'border-edge text-slate-400 hover:border-neon/40 hover:text-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
            <input type="search" name="q" value="{{ $search }}" placeholder="Search events, venues…"
                   class="input-cyber !w-52 !py-1.5 text-sm">
            <button class="btn-ghost rounded-lg px-3 py-1.5 text-sm">Search</button>
        </form>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($listings as $listing)
            @php
                $tiers = $listing->ticketTypes;
                $cheapest = $listing->isService()
                    ? ($listing->services->min('price_minor') ?? 0)
                    : ($tiers->min('price_minor') ?? 0);
                $remaining = $listing->isService()
                    ? $listing->services->sum(fn ($service) => $service->slots->sum(fn ($slot) => $slot->remaining()))
                    : $tiers->sum(fn ($t) => max(0, $t->capacity - $t->sold_count - $t->held_count));
                $listingFeeBps = $listing->organizer?->effectiveCommissionBps() ?? (int) setting('platform_commission_bps', 2000);
            @endphp
            <article class="panel panel-hover flex flex-col overflow-hidden">
                <div class="relative h-40 overflow-hidden border-b border-edge/70 bg-gradient-to-br from-neon/25 via-transparent to-pulse/25">
                    <div class="absolute inset-0 grid-lines opacity-60"></div>
                    <div class="absolute left-4 top-4 flex gap-2">
                        <span class="pill {{ $listing->isEvent() ? 'pill-live' : 'pill-info' }}">
                            {{ $listing->isEvent() ? 'Event' : 'Service' }}
                        </span>
                        @if($remaining > 0 && $remaining <= 12)
                            <span class="pill pill-pending">Only {{ $remaining }} left!</span>
                        @elseif($remaining === 0)
                            <span class="pill pill-closed">Sold out</span>
                        @endif
                    </div>
                    <div class="absolute bottom-3 left-4 right-4">
                        <h3 class="font-display text-lg font-bold leading-tight text-white drop-shadow">{{ $listing->title }}</h3>
                        <p class="text-xs text-slate-300/80">{{ $listing->venue }}@if($listing->city) • {{ $listing->city }}@endif</p>
                    </div>
                </div>

                <div class="flex flex-1 flex-col gap-4 p-5">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">{{ $listing->starts_at->format('D d M • H:i') }}</span>
                        <span class="font-display text-lg font-bold text-neon">@money($cheapest)</span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-edge/70 bg-void/50 px-3 py-2 text-xs">
                        <span class="text-slate-400">Platform fee</span>
                        <span class="font-semibold {{ $listingFeeBps === 0 ? 'text-acid' : 'text-slate-200' }}">
                            {{ \App\Support\Money::percent($listingFeeBps) }}
                        </span>
                    </div>

                    @if($tiers->isNotEmpty())
                        <div class="space-y-1.5 rounded-xl border border-edge/70 bg-void/50 p-3">
                            @foreach($tiers as $tier)
                                @php $left = max(0, $tier->capacity - $tier->sold_count - $tier->held_count); @endphp
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-300">{{ $tier->name }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-slate-400">@money($tier->price_minor)</span>
                                        <span class="pill {{ $left === 0 ? 'pill-closed' : ($left <= 12 ? 'pill-pending' : 'pill-paid') }}">
                                            {{ $left === 0 ? 'Sold out' : $left.' left' }}
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-auto">
                        <button type="button" onclick="toggleAccordion('acc-{{ $listing->id }}')" class="btn-neon w-full rounded-xl px-4 py-2.5 text-sm">
                            Get tickets
                        </button>

                        <div id="acc-{{ $listing->id }}" class="accordion-body mt-3">
                            <form method="POST" action="{{ route('checkout.store') }}" class="space-y-3 rounded-xl border border-neon/25 bg-void/60 p-4">
                                @csrf
                                <input type="hidden" name="listing_id" value="{{ $listing->id }}">

                                @if($tiers->isNotEmpty())
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Ticket tier</label>
                                        <select name="ticket_type_id" class="input-cyber text-sm" required>
                                            @foreach($tiers as $tier)
                                                <option value="{{ $tier->id }}">{{ $tier->name }} — @money($tier->price_minor)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if($listing->isService() && $listing->services->isNotEmpty())
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Appointment slot</label>
                                        <select name="service_slot_id" class="input-cyber text-sm" required>
                                            @foreach($listing->services as $service)
                                                @foreach($service->slots as $slot)
                                                    @if($slot->remaining() > 0 && $slot->starts_at->isFuture())
                                                        <option value="{{ $slot->id }}">
                                                            {{ $slot->starts_at->format('D d M Y • H:i') }} — {{ $service->name }} (@money($service->price_minor))
                                                        </option>
                                                    @endif
                                                @endforeach
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Quantity</label>
                                        <input type="number" name="quantity" min="1" max="{{ $listing->max_per_order }}" value="1" class="input-cyber text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Mobile Money</label>
                                        <select name="momo_provider" class="input-cyber text-sm" required>
                                            @foreach(config('ticketing.momo.providers') as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">MoMo number</label>
                                    <input type="tel" name="buyer_phone" placeholder="0772 123 456" class="input-cyber text-sm" required>
                                    <p class="mt-1 text-xs text-slate-500">{{ config('ticketing.momo.provider_hint') }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Full name</label>
                                        <input type="text" name="buyer_name" class="input-cyber text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
                                        <input type="email" name="buyer_email" class="input-cyber text-sm" required>
                                    </div>
                                </div>

                                @guest
                                    <p class="text-xs text-amber">You need an account to hold tickets. <a class="underline" href="{{ route('login') }}">Sign in</a> to continue.</p>
                                @endguest

                                <button class="btn-neon w-full rounded-xl px-4 py-2.5 text-sm" @guest disabled @endguest>
                                    Request MoMo PIN Prompt
                                </button>
                            </form>
                        </div>
                    </div>

                    <a href="{{ route('events.show', $listing->slug) }}" class="text-center text-xs uppercase tracking-wider text-slate-500 hover:text-neon">
                        View event details →
                    </a>
                </div>
            </article>
        @empty
            <div class="col-span-full panel p-12 text-center">
                <p class="font-display text-xl font-bold">No live listings yet</p>
                <p class="mt-2 text-sm text-slate-500">Publishers are preparing the calendar. Check back shortly.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $listings->links() }}
    </div>
</section>

<section class="border-t border-edge/70 bg-void/40">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <h2 class="font-display text-2xl font-bold">Built for organizers</h2>
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach([
                ['Instant settlement', 'Every Mobile Money payment splits automatically — organizer share straight to your wallet, platform fee itemised.'],
                ['No overselling', 'Inventory is reserved inside a database transaction, so a spike of buyers can never oversell a tier.'],
                ['Gate-ready QR', 'Every ticket carries a unique cryptographic hash. Scanning validates against the server and logs check-ins.'],
            ] as [$title, $body])
                <div class="panel p-6">
                    <h3 class="font-display text-lg font-bold text-neon">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    function toggleAccordion(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('open');
        const button = el.previousElementSibling;
        if (button && button.tagName === 'BUTTON') {
            button.textContent = el.classList.contains('open') ? 'Close' : 'Get tickets';
        }
    }
</script>
@endpush
