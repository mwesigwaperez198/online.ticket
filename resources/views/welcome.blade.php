@extends('layouts.app')

@php
    $platformFeeLabel = \App\Support\Money::percent((int) setting('platform_commission_bps', 2000));
    $liveProviders = collect(config('ticketing.momo.providers'))->keys()
        ->map(fn (string $key): string => $key === 'mtn' ? 'MTN' : ucfirst($key));
@endphp

@section('title', 'Novara Tickets — Buy Event Tickets with Mobile Money in Uganda')
@section('meta_description', 'Buy event tickets and book services across Uganda. Pay instantly with MTN Mobile Money. QR ticket delivered in seconds.')

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden border-b border-edge/60">
    <div class="absolute inset-0 grid-dot opacity-40 pointer-events-none"></div>
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-neon/8 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
        <div class="max-w-2xl animate-fade-up">
            <div class="flex items-center gap-2 mb-6">
                <span class="pill pill-info">Uganda</span>
                <span class="pill pill-live">MTN MoMo</span>
                <span class="pill pill-pending">Live now</span>
            </div>

            <h1 class="font-display text-5xl font-black leading-[1.02] tracking-tight text-slate-100 sm:text-7xl">
                Kampala's<br>
                <span class="neon-text">ticket hub.</span>
            </h1>

            <p class="mt-6 text-lg text-slate-400 leading-relaxed max-w-xl">
                Events, concerts, services — pay with Mobile Money and get your QR ticket in seconds. No bank card needed.
            </p>

            <form method="GET" action="{{ route('home') }}" class="mt-8 flex max-w-lg gap-2">
                <input type="search" name="q" value="{{ $search }}"
                       placeholder="Search events, venues, artists…"
                       class="input-cyber flex-1 !rounded-xl">
                <button class="btn-primary rounded-xl px-6 py-2.5 text-sm shrink-0">Search</button>
            </form>

            <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4 max-w-xl">
                @foreach([
                    ['Events live', number_format($listings->total())],
                    ['MoMo pay', $liveProviders->implode(' / ')],
                    ['Platform fee', $platformFeeLabel],
                    ['QR delivery', 'Instant'],
                ] as [$label, $value])
                    <div class="panel px-4 py-3">
                        <p class="text-[.65rem] uppercase tracking-widest text-slate-500">{{ $label }}</p>
                        <p class="mt-1 font-display text-base font-bold text-slate-100">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Our Picks --}}
@if($picks->isNotEmpty())
<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="flex items-end justify-between gap-4 mb-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-neon font-semibold mb-1">Handpicked</p>
            <h2 class="font-display text-2xl font-black text-slate-100 sm:text-3xl">Our Picks</h2>
        </div>
        <a href="#all-events" class="text-sm text-slate-500 hover:text-neon transition">See all →</a>
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($picks as $i => $pick)
            <div style="animation: fade-up .4s ease {{ $i * 60 }}ms both">
                @include('partials.listing-card', ['listing' => $pick, 'featured' => true])
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- All Events --}}
<section id="all-events" class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold mb-1">Browse</p>
            <h2 class="font-display text-2xl font-black text-slate-100 sm:text-3xl">All Events</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @foreach(['all' => 'All', 'event' => 'Events', 'service' => 'Services'] as $key => $label)
                <a href="{{ route('home', array_filter(['type' => $key === 'all' ? null : $key, 'q' => $search])) }}"
                   class="rounded-lg px-3 py-1.5 text-xs font-medium uppercase tracking-wider transition
                          {{ ($type === $key || ($type === '' && $key === 'all'))
                              ? 'bg-neon/10 text-neon border border-neon/30'
                              : 'border border-edge text-slate-500 hover:text-slate-200 hover:border-slate-500' }}">
                    {{ $label }}
                </a>
            @endforeach
            <input type="search" name="q" form="search-form" value="{{ $search }}"
                   placeholder="Search…" class="input-cyber !w-44 !py-1.5 !text-sm">
            <form id="search-form" method="GET">
                <button class="btn-outline rounded-lg px-3 py-1.5 text-sm">Go</button>
            </form>
        </div>
    </div>

    <div id="events-grid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($listings as $i => $listing)
            <div style="animation: fade-up .4s ease {{ ($i % 8) * 50 }}ms both">
                @include('partials.listing-card', ['listing' => $listing, 'featured' => false])
            </div>
        @empty
            <div class="col-span-full panel p-16 text-center">
                <p class="font-display text-xl font-bold text-slate-300">No events yet</p>
                <p class="mt-2 text-sm text-slate-500">Organizers are loading up the calendar. Check back soon.</p>
            </div>
        @endforelse
    </div>

    @if($listings->hasMorePages())
        <div class="mt-10 flex justify-center">
            <button id="load-more"
                    data-next="{{ $listings->currentPage() + 1 }}"
                    data-url="{{ route('events.load-more', array_filter(['q' => $search, 'type' => $type])) }}"
                    class="btn-outline rounded-xl px-8 py-3 text-sm">
                Load more events
            </button>
        </div>
    @endif
</section>

{{-- Why Novara --}}
<section class="border-t border-edge/60 bg-panel/40">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="max-w-xl mb-10">
            <p class="text-xs uppercase tracking-widest text-neon font-semibold mb-2">Built for Uganda</p>
            <h2 class="font-display text-2xl font-black text-slate-100 sm:text-3xl">Why organizers choose Novara</h2>
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            @foreach([
                ['💸', 'Instant MoMo settlement', 'Every sale splits automatically — your share goes straight to your wallet, platform fee itemised separately.'],
                ['🎟️', 'Zero overselling', 'Inventory is locked inside a database transaction. A spike of buyers can never oversell a tier.'],
                ['📱', 'Gate-ready QR', 'Every ticket carries a unique cryptographic hash. Scan at the gate, log check-ins, no paper needed.'],
            ] as [$icon, $title, $body])
                <div class="panel p-6 panel-lift">
                    <span class="text-2xl">{{ $icon }}</span>
                    <h3 class="mt-3 font-display text-base font-bold text-slate-100">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-500 leading-relaxed">{{ $body }}</p>
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
        button.disabled = true;
        const orig = button.textContent;
        button.textContent = 'Loading…';

        try {
            const sep = url.includes('?') ? '&' : '?';
            const res = await fetch(url + sep + 'page=' + page, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            if (!res.ok) throw new Error();
            const data = await res.json();

            if (data.html && data.html.trim()) {
                const wrap = document.createElement('div');
                wrap.innerHTML = data.html.trim();
                const cards = Array.from(wrap.children);
                cards.forEach((c, i) => {
                    c.style.cssText = `opacity:0;transform:translateY(14px);transition:opacity .35s ease ${i*50}ms,transform .35s ease ${i*50}ms`;
                });
                grid.append(...cards);
                requestAnimationFrame(() => cards.forEach(c => { c.style.opacity='1'; c.style.transform='translateY(0)'; }));
            }

            if (data.last || !data.next) { button.remove(); return; }
            page = data.next;
            button.disabled = false;
            button.textContent = orig;
        } catch {
            button.disabled = false;
            button.textContent = orig;
        }
        loading = false;
    }

    button.addEventListener('click', loadMore);

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '200px' });
        io.observe(button);
    }
})();
</script>
@endpush
