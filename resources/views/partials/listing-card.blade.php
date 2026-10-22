@props(['listing', 'featured' => false])

@php
    $tiers     = $listing->ticketTypes ?? collect();
    $cheapest  = $listing->isService()
        ? ($listing->services->min('price_minor') ?? 0)
        : ($tiers->min('price_minor') ?? 0);
    $remaining = $listing->isService()
        ? $listing->services->sum(fn ($s) => $s->slots->sum(fn ($sl) => $sl->remaining()))
        : $tiers->sum(fn ($t) => max(0, $t->capacity - $t->sold_count - $t->held_count));
@endphp

<article class="panel panel-lift group flex flex-col overflow-hidden h-full {{ $featured ? 'border-neon/20' : 'border-edge/80' }}">
    <a href="{{ route('events.show', $listing->slug) }}" class="relative block overflow-hidden">
        @if($listing->coverImageUrl())
            <img src="{{ $listing->coverImageUrl() }}"
                 alt="{{ $listing->title }}"
                 class="w-full object-cover transition duration-500 group-hover:scale-105 {{ $featured ? 'aspect-[16/9]' : 'aspect-video' }}"
                 loading="lazy">
        @else
            <div class="relative overflow-hidden bg-gradient-to-br from-neon/15 via-void to-pulse/15 {{ $featured ? 'aspect-[16/9]' : 'aspect-video' }}">
                <div class="absolute inset-0 grid-dot opacity-30"></div>
                <span class="absolute inset-0 flex items-center justify-center px-6 text-center font-display text-lg font-bold text-white/70 leading-tight">
                    {{ $listing->title }}
                </span>
            </div>
        @endif

        {{-- Gradient overlay at bottom of image --}}
        <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/60 to-transparent"></div>

        {{-- Badges --}}
        <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
            <span class="pill {{ $listing->isEvent() ? 'pill-live' : 'pill-info' }}">
                {{ $listing->isEvent() ? 'Event' : 'Service' }}
            </span>
            @if($featured)
                <span class="pill pill-pulse">Our pick</span>
            @endif
        </div>

        @if($remaining === 0)
            <div class="absolute inset-0 bg-void/60 flex items-center justify-center">
                <span class="pill pill-closed text-sm px-4 py-1.5">Sold out</span>
            </div>
        @elseif($remaining <= 10)
            <div class="absolute bottom-3 right-3">
                <span class="pill pill-pending">{{ $remaining }} left</span>
            </div>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-4 gap-3">
        <div class="flex-1">
            <h3 class="font-display font-bold text-slate-100 leading-snug group-hover:text-neon transition text-base">
                <a href="{{ route('events.show', $listing->slug) }}">{{ $listing->title }}</a>
            </h3>
            <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                {{ $listing->starts_at->format('D d M · H:i') }}
                @if($listing->venue || $listing->city)
                    · {{ $listing->venue }}@if($listing->city), {{ $listing->city }}@endif
                @endif
            </p>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-edge/60">
            <div>
                <p class="text-[.65rem] uppercase tracking-widest text-slate-600">From</p>
                <p class="font-display font-bold text-neon text-lg leading-none">@money($cheapest)</p>
            </div>
            <a href="{{ route('events.show', $listing->slug) }}"
               class="btn-outline rounded-lg px-3 py-1.5 text-xs font-medium">
                Get tickets
            </a>
        </div>
    </div>
</article>
