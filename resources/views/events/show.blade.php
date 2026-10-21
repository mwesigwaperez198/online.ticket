@extends('layouts.app')

@section('title', $listing->title.' — Pulse Tickets')

@section('content')
<section class="border-b border-edge/70">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.25fr_1fr]">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="pill {{ $listing->isEvent() ? 'pill-live' : 'pill-info' }}">{{ $listing->isEvent() ? 'Event' : 'Service' }}</span>
                    <span class="pill pill-pending">{{ $listing->starts_at->format('D d M Y • H:i') }}</span>
                    @if($listing->venue)<span class="pill pill-info">{{ $listing->venue }}</span>@endif
                </div>

                <h1 class="mt-5 font-display text-3xl font-black leading-tight sm:text-5xl">{{ $listing->title }}</h1>
                <p class="mt-4 max-w-2xl text-slate-400">{{ $listing->description }}</p>

                <p class="mt-6 text-xs uppercase tracking-wider text-slate-500">
                    Presented by <span class="text-slate-200">{{ $listing->organizer->business_name }}</span>
                </p>

                @if($listing->isService() && $listing->services->isNotEmpty())
                    <div class="mt-8 space-y-3">
                        <h2 class="font-display text-lg font-bold">Available slots</h2>
                        @foreach($listing->services as $service)
                            <div class="panel p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="font-display font-bold">{{ $service->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $service->duration_minutes }} min session</p>
                                    </div>
                                    <span class="font-display text-lg font-bold text-neon">GHS {{ number_format($service->price_minor / 100, 2) }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($service->slots as $slot)
                                        <span class="pill {{ $slot->remaining() > 0 ? 'pill-paid' : 'pill-closed' }}">
                                            {{ $slot->starts_at->format('d M H:i') }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="panel p-6 lg:sticky lg:top-24">
                <h2 class="font-display text-lg font-bold">{{ $listing->isService() ? 'Choose a slot' : 'Choose your tier' }}</h2>

                <div class="mt-4 space-y-3">
                    @foreach($ticketTypes as $tier)
                        @php $left = max(0, $tier->capacity - $tier->sold_count - $tier->held_count); @endphp
                        <div class="rounded-xl border border-edge/80 bg-void/50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-display font-bold text-slate-100">{{ $tier->name }}</p>
                                    @if($tier->description)<p class="mt-1 text-xs text-slate-500">{{ $tier->description }}</p>@endif
                                </div>
                                <span class="font-display text-lg font-bold text-neon">GHS {{ number_format($tier->price_minor / 100, 2) }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="pill {{ $left === 0 ? 'pill-closed' : ($left <= 12 ? 'pill-pending' : 'pill-paid') }}">
                                    {{ $left === 0 ? 'Sold out' : ($left <= 12 ? 'Only '.$left.' tickets left!' : $left.' tickets left') }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $tier->sold_count }} sold</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" onclick="toggleAccordion('acc-checkout')" class="btn-neon mt-6 w-full rounded-xl px-4 py-3">
                    Checkout with Mobile Money
                </button>

                <div id="acc-checkout" class="accordion-body mt-4">
                    <form method="POST" action="{{ route('checkout.store') }}" class="space-y-3 rounded-xl border border-neon/25 bg-void/60 p-4">
                        @csrf
                        <input type="hidden" name="listing_id" value="{{ $listing->id }}">

                        @if($ticketTypes->isNotEmpty())
                            <div>
                                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Ticket tier</label>
                                <select name="ticket_type_id" class="input-cyber text-sm" required>
                                    @foreach($ticketTypes as $tier)
                                        <option value="{{ $tier->id }}">{{ $tier->name }} — GHS {{ number_format($tier->price_minor / 100, 2) }}</option>
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
                                                    {{ $slot->starts_at->format('D d M Y • H:i') }} — {{ $service->name }} (GHS {{ number_format($service->price_minor / 100, 2) }})
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
                            <input type="tel" name="buyer_phone" placeholder="024 000 0000" class="input-cyber text-sm" required>
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

                        <p class="text-xs text-slate-500">
                            Platform fee {{ rtrim(rtrim(number_format($commissionBps / 100, 2), '0'), '.') }}% is deducted from the organizer's share. You pay the ticket price.
                        </p>

                        @guest
                            <p class="text-xs text-amber">Sign in to complete the purchase. <a class="underline" href="{{ route('login') }}">Sign in</a></p>
                        @endguest

                        <button class="btn-neon w-full rounded-xl px-4 py-2.5 text-sm" @guest disabled @endguest>
                            Confirm &amp; pay
                        </button>
                    </form>
                </div>
            </aside>
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
            button.textContent = el.classList.contains('open') ? 'Close checkout' : 'Checkout with Mobile Money';
        }
    }
</script>
@endpush
