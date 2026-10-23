@extends('layouts.app')

@section('title', 'Review listing — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-neon hover:underline">← Back to dashboard</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500">Listing review</p>
            <h1 class="mt-1 font-display text-3xl font-black">{{ $listing->title }}</h1>
            <p class="mt-2 text-sm text-slate-400">Submitted by {{ $listing->organizer->business_name }} · {{ $listing->created_at->format('d M Y H:i') }}</p>
        </div>
        <span class="pill {{ $listing->status === 'pending' ? 'pill-pending' : 'pill-info' }}">{{ $listing->status }}</span>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="panel overflow-hidden">
                @if($listing->coverImageUrl())
                    <img src="{{ $listing->coverImageUrl() }}" alt="{{ $listing->title }} banner" class="aspect-video w-full object-cover">
                @else
                    <div class="relative grid aspect-video w-full place-items-center overflow-hidden bg-linear-to-br from-neon/15 via-void to-pulse/15">
                        <div class="absolute inset-0 grid-dot opacity-30"></div>
                        <span class="absolute inset-0 flex items-center justify-center px-6 text-center font-display text-xl font-black leading-tight text-white/70">
                            {{ $listing->title }}
                        </span>
                    </div>
                @endif
                <div class="space-y-3 p-6">
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ ucfirst($listing->type) }} description</p>
                    <p class="whitespace-pre-line text-sm leading-6 text-slate-200">{{ $listing->description ?: 'No description provided.' }}</p>
                    <dl class="grid gap-3 border-t border-edge/70 pt-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Venue</dt><dd>{{ $listing->venue ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Address</dt><dd>{{ $listing->venue_address ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">City / region</dt><dd>{{ collect([$listing->city, $listing->region])->filter()->join(', ') ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Date</dt><dd>{{ $listing->starts_at->format('D d M Y · H:i') }}@if($listing->ends_at) – {{ $listing->ends_at->format('D d M Y · H:i') }}@endif</dd></div>
                        <div><dt class="text-xs text-slate-500">Coordinates</dt><dd>{{ $listing->hasCoordinates() ? $listing->latitude.', '.$listing->longitude : 'Not provided' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Refund terms</dt><dd>{{ $listing->refund_window_hours }} hour window · max {{ $listing->max_per_order }} per order</dd></div>
                    </dl>
                    @if($listing->video_url)
                        <p class="break-all text-xs text-slate-400">Trailer: {{ $listing->video_url }}</p>
                    @endif
                </div>
            </div>

            @if($listing->images->isNotEmpty())
                <div class="panel p-6">
                    <h2 class="font-display text-lg font-bold">Event gallery</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($listing->images as $image)
                            <img src="{{ $image->url() }}" alt="{{ $listing->title }} gallery photo {{ $loop->iteration }}" class="aspect-video w-full rounded-lg object-cover">
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="panel p-6">
                <h2 class="font-display text-lg font-bold">Pricing and capacity</h2>
                @if($listing->isEvent())
                    <div class="mt-4 space-y-2">
                        @forelse($listing->ticketTypes as $tier)
                            <div class="flex flex-wrap justify-between gap-2 rounded-lg border border-edge/70 px-4 py-3 text-sm">
                                <span class="font-semibold">{{ $tier->name }}</span>
                                <span>@money($tier->price_minor)</span>
                                <span class="text-slate-400">{{ $tier->capacity }} capacity</span>
                            </div>
                        @empty
                            <p class="text-sm text-amber">No ticket tiers provided.</p>
                        @endforelse
                    </div>
                @else
                    <div class="mt-4 space-y-2">
                        @forelse($listing->services as $service)
                            <div class="flex justify-between gap-3 text-sm"><span>{{ $service->name }}</span><span>@money($service->price_minor)</span></div>
                        @empty
                            <p class="text-sm text-slate-400">No service items provided.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            <div class="panel p-6">
                <h2 class="font-display text-lg font-bold">Organizer / merchant</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Business</dt><dd>{{ $listing->organizer->business_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Contact</dt><dd>{{ $listing->organizer->user?->name }} · {{ $listing->organizer->user?->email }} · {{ $listing->organizer->user?->phone }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Merchant status</dt><dd>{{ ucfirst($listing->organizer->status) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Payout account</dt><dd>{{ $listing->organizer->payout_provider ? config('ticketing.momo.providers.'.$listing->organizer->payout_provider, $listing->organizer->payout_provider) : 'Provider not set' }} · {{ $listing->organizer->payout_momo_number ?: 'Number not provided' }}</dd><dd class="text-slate-400">{{ $listing->organizer->payout_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Payout approval</dt><dd>{{ $listing->organizer->payout_approved_at ? 'Approved '.$listing->organizer->payout_approved_at->format('d M Y') : 'Awaiting approval / not approved' }}</dd></div>
                    @foreach($listing->organizer->socialLinks() as $network => $link)
                        <div><dt class="text-xs text-slate-500">{{ $network }}</dt><dd class="break-all">{{ $link }}</dd></div>
                    @endforeach
                    @if($listing->organizer->notes)
                        <div><dt class="text-xs text-slate-500">Admin notes</dt><dd>{{ $listing->organizer->notes }}</dd></div>
                    @endif
                </dl>
                <a href="{{ route('admin.approvals.index') }}" class="mt-5 inline-block text-xs text-neon hover:underline">Open organizer approvals →</a>
            </div>

            <form method="POST" action="{{ route('admin.listings.moderate', $listing) }}" class="panel space-y-4 p-6">
                @csrf
                <h2 class="font-display text-lg font-bold">Decision and commission</h2>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Listing decision</label>
                    <select name="status" class="input-cyber text-sm">
                        <option value="pending" @selected($listing->status === 'pending')>Keep pending</option>
                        <option value="published" @selected(in_array($listing->status, ['published', 'live'], true))>Approve and publish</option>
                        <option value="draft" @selected($listing->status === 'draft')>Return to draft for changes</option>
                        <option value="cancelled" @selected($listing->status === 'cancelled')>Reject / cancel</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Organizer commission (%)</label>
                    <input type="number" name="commission_percent" step="0.01" min="0" max="100"
                              value="{{ $listing->organizer->commission_bps !== null ? rtrim(rtrim(number_format($listing->organizer->commission_bps / 100, 2), '0'), '.') : '' }}"
                              placeholder="{{ rtrim(rtrim(number_format((int) setting('platform_commission_bps', 2000) / 100, 2), '0'), '.') }}" class="input-cyber text-sm">
                          <p class="mt-1 text-xs text-slate-500">Leave blank to use the current platform rate. An entered value becomes this organizer’s override for future sales.</p>
                </div>
                <button class="btn-neon w-full rounded-xl px-5 py-3">Save decision</button>
            </form>
            <form method="POST" action="{{ route('admin.listings.destroy', $listing) }}" class="panel p-5" onsubmit="return confirm('Cancel and archive this listing from the marketplace? Historical orders and payment records remain available.');">
                @csrf
                @method('DELETE')
                <button class="btn-ghost w-full rounded-xl px-4 py-2.5 text-sm text-red-300">Cancel and archive listing</button>
            </form>
        </aside>
    </div>
</section>
@endsection
