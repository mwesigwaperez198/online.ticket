@extends('layouts.app')

@section('title', ($listing->exists ? 'Edit listing' : 'New listing').' — Novara Tickets')

@section('content')
<section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">{{ $listing->exists ? 'Edit listing' : 'New listing' }}</h1>
    <p class="mt-1 text-sm text-slate-500">Events sell ticket tiers. Services sell bookable slots.</p>

    <form method="POST" action="{{ $listing->exists ? route('organizer.listings.update', $listing) : route('organizer.listings.store') }}" class="mt-8 space-y-6">
        @csrf
        @if($listing->exists) @method('PUT') @endif

        <div class="panel space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Type</label>
                    <select name="type" class="input-cyber text-sm" @if($listing->exists) disabled @endif>
                        <option value="event" @selected($listing->type === 'event')>Event (ticket tiers)</option>
                        <option value="service" @selected($listing->type === 'service')>Service (appointments)</option>
                    </select>
                    @if($listing->exists)<input type="hidden" name="type" value="{{ $listing->type }}">@endif
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Title</label>
                    <input type="text" name="title" value="{{ old('title', $listing->title) }}" required class="input-cyber text-sm">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Description</label>
                <textarea name="description" rows="4" class="input-cyber text-sm">{{ old('description', $listing->description) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Venue</label>
                    <input type="text" name="venue" value="{{ old('venue', $listing->venue) }}" class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">City</label>
                    <input type="text" name="city" value="{{ old('city', $listing->city) }}" class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Region</label>
                    <input type="text" name="region" value="{{ old('region', $listing->region) }}" class="input-cyber text-sm">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Venue address</label>
                <input type="text" name="venue_address" value="{{ old('venue_address', $listing->venue_address) }}"
                       placeholder="e.g. Plot 30, Lugogo Cricket Oval, Kampala" class="input-cyber text-sm">
                <p class="mt-1 text-xs text-slate-500">Shown on the event page with directions to the gate.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Latitude</label>
                    <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $listing->latitude) }}"
                           placeholder="0.3100000" class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Longitude</label>
                    <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $listing->longitude) }}"
                           placeholder="32.5750000" class="input-cyber text-sm">
                </div>
            </div>
            <p class="text-xs text-slate-500">
                Both coordinates pin the venue on the live map. Open Google Maps, right click the venue and copy the
                numbers after the decimal point.
            </p>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Trailer video link (optional)</label>
                <input type="url" name="video_url" value="{{ old('video_url', $listing->video_url) }}"
                       placeholder="https://www.youtube.com/watch?v=…" class="input-cyber text-sm">
                <p class="mt-1 text-xs text-slate-500">
                    Paste a YouTube or Vimeo link. It plays inline on your event page as a trailer.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Starts at</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $listing->exists ? $listing->starts_at->format('Y-m-d\TH:i') : '') }}" required class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Ends at</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $listing->exists && $listing->ends_at ? $listing->ends_at->format('Y-m-d\TH:i') : '') }}" class="input-cyber text-sm">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Max tickets per order</label>
                    <input type="number" name="max_per_order" min="1" max="20" value="{{ old('max_per_order', $listing->max_per_order ?? 10) }}" required class="input-cyber text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Refund window (hours)</label>
                    <input type="number" name="refund_window_hours" min="0" max="720" value="{{ old('refund_window_hours', $listing->refund_window_hours ?? 72) }}" required class="input-cyber text-sm">
                </div>
            </div>
        </div>

        @if(! $listing->exists)
            <div class="panel p-6">
                <h2 class="font-display text-lg font-bold">Ticket tiers</h2>
                <p class="mt-1 text-xs text-slate-500">Add up to four tiers with capacity and price in Ugandan shillings.</p>

                <div class="mt-4 space-y-3">
                    @for($i = 0; $i < 4; $i++)
                        <div class="grid gap-3 sm:grid-cols-[1.4fr_1fr_1fr]">
                            <input type="text" name="ticket_types[{{ $i }}][name]" placeholder="Tier name (e.g. Regular)" class="input-cyber text-sm">
                            <input type="number" step="0.01" min="0" name="ticket_types[{{ $i }}][price]" placeholder="Price (UGX)" class="input-cyber text-sm">
                            <input type="number" min="1" name="ticket_types[{{ $i }}][capacity]" placeholder="Capacity" class="input-cyber text-sm">
                        </div>
                    @endfor
                </div>
            </div>
        @else
            <div class="panel p-6">
                <h2 class="font-display text-lg font-bold">Ticket tiers</h2>
                <div class="mt-4 space-y-2">
                    @forelse($ticketTypes as $tier)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-edge/80 bg-void/50 px-4 py-3 text-sm">
                            <span class="text-slate-200">{{ $tier->name }}</span>
                            <span class="text-slate-400">@money($tier->price_minor)</span>
                            <span class="text-slate-500">{{ $tier->sold_count }} sold / {{ $tier->capacity }} capacity</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No tiers on this listing yet.</p>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <button class="btn-neon rounded-xl px-6 py-3">Save listing</button>
            <a href="{{ route('organizer.listings.index') }}" class="btn-ghost rounded-xl px-6 py-3">Cancel</a>
        </div>
    </form>

    @if($listing->exists)
        <form method="POST" action="{{ route('organizer.listings.cover', $listing) }}" enctype="multipart/form-data"
              class="panel mt-6 space-y-4 p-6">
            @csrf

            <div>
                <h2 class="font-display text-lg font-bold">Event banner</h2>
                <p class="mt-1 text-xs text-slate-500">
                    JPEG, PNG or WebP only, up to 2 MB. The file type is checked from its content, not its name.
                </p>
            </div>

            @if($listing->coverImageUrl())
                <img src="{{ $listing->coverImageUrl() }}" alt="Current banner" class="aspect-video w-full max-w-md rounded-xl border border-edge/80 object-cover">
            @endif

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Choose image</label>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" required
                       class="block w-full text-sm text-slate-400 file:mr-4 file:rounded-lg file:border-0 file:bg-neon/15 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-neon hover:file:bg-neon/25">
            </div>

            <button class="btn-ghost rounded-xl px-5 py-2.5 text-sm">Upload banner</button>
        </form>
    @endif
</section>
@endsection
