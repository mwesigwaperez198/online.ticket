@extends('layouts.app')

@section('title', 'Become a seller — Novara Tickets')

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <span class="pill pill-info">Seller workspace</span>
        <h1 class="mt-4 font-display text-2xl font-black">Start selling on Novara</h1>
        <p class="mt-2 text-sm text-slate-500">
            Your buyer account doubles as a seller account — same sign-in, same tickets, no second registration.
            Pick a business name and your workspace opens right away; listings go live after platform moderation.
        </p>

        <form method="POST" action="{{ route('seller.onboarding.store') }}" class="mt-7 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Business or promoter name</label>
                <input type="text" name="business_name" value="{{ old('business_name') }}" required autofocus
                       maxlength="160" placeholder="e.g. DA-VELA Events" class="input-cyber text-sm">
            </div>

            @error('business_name')
                <p class="text-xs text-pulse">{{ $message }}</p>
            @enderror

            <button class="btn-neon w-full rounded-xl px-4 py-3">Open my seller workspace</button>
        </form>

        <p class="mt-6 text-xs leading-relaxed text-slate-500">
            Selling needs no approval to start building: create listings, upload posters and trailers, then submit
            for moderation. Admin reviews organizers before payouts.
            Questions? WhatsApp <a href="https://wa.me/256765866555" class="text-neon hover:underline">{{ config('ticketing.brand.whatsapp', '+256765866555') }}</a>.
        </p>
    </div>
</section>
@endsection
