@props(['listing', 'featured' => false])

@php
    $tiers = $listing->ticketTypes ?? collect();
    $cheapest = $listing->isService()
        ? ($listing->services->min('price_minor') ?? 0)
        : ($tiers->min('price_minor') ?? 0);
    $remaining = $listing->isService()
        ? $listing->services->sum(fn ($service) => $service->slots->sum(fn ($slot) => $slot->remaining()))
        : $tiers->sum(fn ($t) => max(0, $t->capacity - $t->sold_count - $t->held_count));
    $accent = $featured ? 'border-neon/50' : 'border-edge/80';
@endphp

<article {{ $attributes->merge(['class' => "panel panel-hover group flex flex-col overflow-hidden border {$accent} ".($featured ? 'shadow-glow' : '')]) }}>
    <a href="{{ route('events.show', $listing->slug) }}" class="relative block">
        @if($listing->coverImageUrl())
            <img src="{{ $listing->coverImageUrl() }}" alt="{{ $listing->title }} poster"
                 class="aspect-video w-full object-cover rounded-t-xl transition duration-500 group-hover:scale-105"
                 loading="lazy">
        @else
            <div class="relative aspect-video w-full overflow-hidden rounded-t-xl bg-gradient-to-br from-neon/25 via-transparent to-pulse/25">
                <div class="absolute inset-0 grid-lines opacity-60"></div>
                <span class="absolute inset-0 grid place-items-center px-4 text-center font-display text-lg font-bold text-white/80">
                    {{ $listing->title }}
                </span>
            </div>
        @endif

        <div class="absolute left-3 top-3 flex flex-wrap gap-2">
            <span class="pill {{ $listing->isEvent() ? 'pill-live' : 'pill-info' }}">
                {{ $listing->isEvent() ? 'Event' : 'Service' }}
            </span>
            @if($featured)
                <span class="pill pill-info">Our pick</span>
            @endif
            @if($remaining > 0 && $remaining <= 12)
                <span class="pill pill-pending">Only {{ $remaining }} left</span>
            @elseif($remaining === 0)
                <span class="pill pill-closed">Sold out</span>
            @endif
        </div>
    </a>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <div>
            <h3 class="font-display text-lg font-bold leading-tight text-slate-100 transition group-hover:text-neon">
                <a href="{{ route('events.show', $listing->slug) }}">{{ $listing->title }}</a>
            </h3>
            <p class="mt-1 text-xs text-slate-500">
                {{ $listing->starts_at->format('D d M • H:i') }}
                @if($listing->venue || $listing->city)
                    <br>{{ $listing->venue }}@if($listing->city) • {{ $listing->city }}@endif
                @endif
            </p>
        </div>

        <div class="mt-auto flex items-center justify-between gap-3">
            <span class="font-display text-lg font-bold text-neon">@money($cheapest)</span>
            <a href="{{ route('events.show', $listing->slug) }}"
               class="btn-ghost rounded-lg px-3 py-1.5 text-xs uppercase tracking-wider">
                View →
            </a>
        </div>
    </div>
</article>
