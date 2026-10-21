@extends('layouts.app')

@section('title', 'My listings — Pulse Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-black">My listings</h1>
            <p class="mt-1 text-sm text-slate-500">Events and services you publish to the marketplace.</p>
        </div>
        <a href="{{ route('organizer.listings.create') }}" class="btn-neon rounded-xl px-4 py-2.5 text-sm">New listing</a>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($listings as $listing)
            @php
                $pill = match ($listing->status) {
                    'published', 'live' => 'pill-live',
                    'pending' => 'pill-pending',
                    'cancelled' => 'pill-closed',
                    default => 'pill-info',
                };
                $remaining = $listing->ticketTypes->sum(fn ($t) => max(0, $t->capacity - $t->sold_count - $t->held_count));
            @endphp
            <article class="panel panel-hover p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-lg font-bold leading-tight">{{ $listing->title }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $listing->starts_at->format('D d M Y • H:i') }}</p>
                    </div>
                    <span class="pill {{ $pill }}">{{ $listing->status }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-400">
                    <div><dt class="text-slate-600">Type</dt><dd class="text-slate-200">{{ $listing->type }}</dd></div>
                    <div><dt class="text-slate-600">Tiers</dt><dd class="text-slate-200">{{ $listing->ticketTypes->count() }}</dd></div>
                    <div><dt class="text-slate-600">Sold</dt><dd class="text-slate-200">{{ $listing->ticketTypes->sum('sold_count') }}</dd></div>
                    <div><dt class="text-slate-600">Remaining</dt><dd class="text-slate-200">{{ $remaining }}</dd></div>
                </dl>

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('organizer.listings.edit', $listing) }}" class="btn-ghost flex-1 rounded-lg px-3 py-2 text-center text-xs uppercase tracking-wider">Edit</a>
                    @if($listing->status === 'draft')
                        <form method="POST" action="{{ route('organizer.listings.publish', $listing) }}" class="flex-1">
                            @csrf
                            <button class="btn-neon w-full rounded-lg px-3 py-2 text-xs uppercase tracking-wider">Submit</button>
                        </form>
                    @endif
                    @if(in_array($listing->status, ['published', 'live'], true))
                        <a href="{{ route('events.show', $listing->slug) }}" class="btn-ghost flex-1 rounded-lg px-3 py-2 text-center text-xs uppercase tracking-wider">View</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full panel p-12 text-center">
                <p class="font-display text-xl font-bold">No listings yet</p>
                <p class="mt-2 text-sm text-slate-500">Create your first event and start selling with Mobile Money.</p>
                <a href="{{ route('organizer.listings.create') }}" class="btn-neon mt-6 inline-block rounded-xl px-6 py-3">Create listing</a>
            </div>
        @endforelse
    </div>
</section>
@endsection
