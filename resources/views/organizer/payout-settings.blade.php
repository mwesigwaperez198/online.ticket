@extends('layouts.app')

@section('title', 'Payout settings — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
    <a href="{{ route('organizer.dashboard') }}" class="text-xs uppercase tracking-wider text-slate-500 hover:text-neon">← Back to dashboard</a>

    <h1 class="mt-4 font-display text-3xl font-black">Payout settings</h1>
    <p class="mt-2 text-sm text-slate-500">
        The Mobile Money account you submit here receives your share of every sale. The admin verifies the number
        before the first payout, and every change sends it back for re-approval.
    </p>

    <div class="mt-6 panel px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Status</p>
                @if($organizer->payout_approved_at)
                    <p class="mt-1 font-display text-lg font-bold text-acid">Approved {{ $organizer->payout_approved_at->format('d M Y') }}</p>
                @elseif($organizer->hasPayoutDetails())
                    <p class="mt-1 font-display text-lg font-bold text-amber">Awaiting admin approval</p>
                @else
                    <p class="mt-1 font-display text-lg font-bold text-slate-300">Not submitted yet</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Your platform fee</p>
                <p class="mt-1 font-display text-lg font-bold text-slate-100">{{ \App\Support\Money::percent($organizer->effectiveCommissionBps()) }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('organizer.payout-settings.update') }}" class="mt-6 panel space-y-5 p-6">
        @csrf

        <div>
            <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Account name</label>
            <input type="text" name="payout_name" value="{{ old('payout_name', $organizer->payout_name) }}" required class="input-cyber text-sm">
            <p class="mt-1 text-xs text-slate-500">The name registered on the Mobile Money account.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Mobile Money provider</label>
                <select name="payout_provider" class="input-cyber text-sm" required>
                    @foreach(config('ticketing.momo.providers') as $key => $label)
                        <option value="{{ $key }}" @selected(old('payout_provider', $organizer->payout_provider) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Receiving number</label>
                <input type="tel" name="payout_momo_number" value="{{ old('payout_momo_number', $organizer->payout_momo_number) }}" placeholder="0772 123 456" required class="input-cyber text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ config('ticketing.momo.provider_hint') }}</p>
            </div>
        </div>

        <div class="divider-glow"></div>

        <div>
            <h2 class="font-display text-lg font-bold">Social handles <span class="text-xs font-normal text-slate-500">(optional)</span></h2>
            <p class="mt-1 text-xs text-slate-500">Shown on your public listings so buyers can reach you directly.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Instagram</label>
                <input type="url" name="instagram" value="{{ old('instagram', $organizer->instagram) }}" placeholder="https://instagram.com/yourpage" class="input-cyber text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">WhatsApp</label>
                <input type="text" name="whatsapp" value="{{ old('whatsapp', $organizer->whatsapp) }}" placeholder="+256772123456" class="input-cyber text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Facebook</label>
                <input type="url" name="facebook" value="{{ old('facebook', $organizer->facebook) }}" placeholder="https://facebook.com/yourpage" class="input-cyber text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Website</label>
                <input type="url" name="website" value="{{ old('website', $organizer->website) }}" placeholder="https://example.com" class="input-cyber text-sm">
            </div>
        </div>

        <button class="btn-neon w-full rounded-xl px-4 py-3">Submit for approval</button>

        <p class="text-xs text-slate-500">
            Submitting creates a request in the platform CMS. The admin approves the number and confirms your
            commission rate before any payout is sent.
        </p>
    </form>
</section>
@endsection
