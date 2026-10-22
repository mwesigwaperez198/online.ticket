@extends('layouts.app')

@php
    $platformFeeLabel = \App\Support\Money::percent((int) setting('platform_commission_bps', 2000));
    $liveProviders = collect(config('ticketing.momo.providers'))->keys()
        ->map(fn (string $key): string => $key === 'mtn' ? 'MTN' : ucfirst($key));
@endphp

@section('title', 'Novara Tickets — Mobile Money events & services in Uganda')
@section('meta_description', 'Novara Tickets buys and sells event tickets, services and gate passes across Uganda. Pay instantly with '.implode(' or ', config('ticketing.momo.providers')).'. Everything by Novara.')

@section('content')
<section class="relative overflow-hidden border-b border-edge/70">
    <div class="absolute inset-0 grid-lines opacity-70"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
        <div class="max-w-3xl">
            <span class="pill pill-info">{{ implode(' · ', config('ticketing.momo.providers')) }} · Uganda</span>
            <h1 class="mt-6 font-display text-4xl font-black leading-[1.05] tracking-tight sm:text-6xl">
                Own the night.<br>
                <span class="neon-text">Sell the tickets.</span>
            </h1>
            <p class="mt-6 max-w-2xl text-base text-slate-400 sm:text-lg">
                Live events, service bookings and gate passes across Uganda — paid instantly with Mobile Money.
                {{ config('ticketing.brand.tagline', 'Everything by Novara.') }}
            </p>

            <form method="GET" action="{{ route('home') }}" class="mt-8 flex max-w-xl flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search events, venues, cities…"
                       class="input-cyber !w-auto flex-1">
                <button class="btn-neon rounded-xl px-6 py-3">Discover</button>
            </form>

            <dl class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach([
                    ['Live listings', number_format($listings->total())],
                    ['Mobile money', $liveProviders->implode(' / ')],
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

@if($picks->isNotEmpty())
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-display text-2xl font-bold sm:text-3xl">Our Picks</h2>
            <p class="mt-1 text-sm text-slate-500">Hand-picked by the Novara team.</p>
        </div>
        <a href="#all-events" class="text-sm uppercase tracking-wider text-neon transition hover:text-acid">See more →</a>
    </div>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($picks as $pick)
            @include('partials.listing-card', ['listing' => $pick, 'featured' => true])
        @endforeach
    </div>
</section>
@endif

<section id="all-events" class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="font-display text-2xl font-bold sm:text-3xl">All Events</h2>
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

    <div id="events-grid" class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($listings as $listing)
            @include('partials.listing-card', ['listing' => $listing, 'featured' => false])
        @empty
            <div class="col-span-full panel p-12 text-center">
                <p class="font-display text-xl font-bold">No live listings yet</p>
                <p class="mt-2 text-sm text-slate-500">Publishers are preparing the calendar. Check back shortly.</p>
            </div>
        @endforelse
    </div>

    @if($listings->hasMorePages())
        <div class="mt-10 flex justify-center">
            <button id="load-more"
                    data-next="{{ $listings->currentPage() + 1 }}"
                    data-url="{{ route('events.load-more', array_filter(['q' => $search, 'type' => $type])) }}"
                    class="btn-neon rounded-xl px-8 py-3">
                Load more
            </button>
        </div>
    @endif
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
    (function () {
        const grid = document.getElementById('events-grid');
        const button = document.getElementById('load-more');
        if (!grid || !button) return;

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        let page = parseInt(button.dataset.next, 10);
        const url = button.dataset.url;
        let loading = false;

        async function loadMore() {
            if (loading) return;
            loading = true;

            const original = button.textContent;
            button.disabled = true;
            button.textContent = 'Loading…';

            try {
                const sep = url.includes('?') ? '&' : '?';
                const response = await fetch(url + sep + 'page=' + page, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });

                if (!response.ok) throw new Error('bad status');

                const data = await response.json();

                if (data.html && data.html.trim() !== '') {
                    const holder = document.createElement('div');
                    holder.innerHTML = data.html.trim();
                    const cards = Array.from(holder.children);

                    cards.forEach((card) => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(12px)';
                        card.style.transition = 'opacity .4s ease, transform .4s ease';
                    });

                    grid.append(...cards);

                    requestAnimationFrame(() => {
                        cards.forEach((card) => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        });
                    });
                }

                if (data.last || !data.next) {
                    button.remove();
                    return;
                }

                page = data.next;
                button.dataset.next = data.next;
                button.disabled = false;
                button.textContent = original;
            } catch (e) {
                button.disabled = false;
                button.textContent = original;
            }

            loading = false;
        }

        button.addEventListener('click', loadMore);

        // Smoothly pull the next page in when the button scrolls into view.
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        loadMore();
                    }
                });
            }, { rootMargin: '200px' });

            const watch = () => {
                const target = document.getElementById('load-more');
                if (target) observer.observe(target);
            };

            watch();
            new MutationObserver(watch).observe(grid, { childList: true });
        }
    })();
</script>
@endpush
