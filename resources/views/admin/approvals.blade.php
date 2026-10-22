@extends('layouts.app')

@section('title', 'Organizer approvals — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Organizer audit</h1>
    <p class="mt-1 text-sm text-slate-500">Approve organizers, confirm their Mobile Money payout account and set the commission they pay.</p>

    <div class="mt-8 space-y-4">
        @forelse($organizers as $organizer)
            @php
                $pill = match ($organizer->status) {
                    'approved' => 'pill-paid',
                    'pending' => 'pill-pending',
                    'suspended' => 'pill-closed',
                    default => 'pill-info',
                };
            @endphp
            <div class="panel p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-lg font-bold">{{ $organizer->business_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $organizer->user?->name }} • {{ $organizer->user?->email }} • {{ $organizer->user?->phone }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Slug: <span class="font-mono">{{ $organizer->slug }}</span></p>
                    </div>
                    <div class="text-right">
                        <span class="pill {{ $pill }}">{{ $organizer->status }}</span>
                        <p class="mt-2 text-xs text-slate-500">
                            Commission
                            <span class="font-semibold text-slate-200">{{ \App\Support\Money::percent($organizer->effectiveCommissionBps()) }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-edge/70 bg-void/50 px-3 py-2">
                        <p class="text-[.65rem] uppercase tracking-wider text-slate-500">Submitted payout number</p>
                        @if($organizer->payout_momo_number)
                            <p class="mt-1 text-sm font-semibold text-slate-100">
                                {{ config('ticketing.momo.providers.'.$organizer->payout_provider, $organizer->payout_provider ?? 'Mobile Money') }} · {{ $organizer->payout_momo_number }}
                            </p>
                            <p class="text-xs text-slate-500">{{ $organizer->payout_name }}</p>
                        @else
                            <p class="mt-1 text-sm text-slate-400">Not submitted</p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-edge/70 bg-void/50 px-3 py-2">
                        <p class="text-[.65rem] uppercase tracking-wider text-slate-500">Payout approval</p>
                        @if($organizer->payout_approved_at)
                            <p class="mt-1 text-sm font-semibold text-acid">Approved {{ $organizer->payout_approved_at->format('d M Y H:i') }}</p>
                        @elseif($organizer->hasPayoutDetails())
                            <p class="mt-1 text-sm font-semibold text-amber">Awaiting approval</p>
                        @else
                            <p class="mt-1 text-sm text-slate-400">No request</p>
                        @endif
                        @if($organizer->payout_submitted_at)
                            <p class="text-xs text-slate-500">Submitted {{ $organizer->payout_submitted_at->format('d M Y H:i') }}</p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-edge/70 bg-void/50 px-3 py-2">
                        <p class="text-[.65rem] uppercase tracking-wider text-slate-500">Social handles</p>
                        @if($organizer->hasSocialLinks())
                            <div class="mt-1 space-y-0.5">
                                @foreach($organizer->socialLinks() as $network => $link)
                                    <p class="truncate text-xs text-slate-300"><span class="text-slate-500">{{ $network }}:</span> {{ $link }}</p>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-1 text-sm text-slate-400">None provided</p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-edge/70 bg-void/50 px-3 py-2">
                        <p class="text-[.65rem] uppercase tracking-wider text-slate-500">Listings</p>
                        <p class="mt-1 text-sm font-semibold text-slate-100">{{ $organizer->listings()->count() }}</p>
                        <p class="text-xs text-slate-500">Wallet balance {{ \App\Support\Money::format((int) ($organizer->wallet?->balance_minor ?? 0)) }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.approvals.update', $organizer) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Status</label>
                        <select name="status" class="input-cyber text-sm">
                            <option value="pending" @selected($organizer->status === 'pending')>Pending</option>
                            <option value="approved" @selected($organizer->status === 'approved')>Approved</option>
                            <option value="suspended" @selected($organizer->status === 'suspended')>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Commission %</label>
                        <input type="number" step="0.01" min="0" max="100" name="commission_percent"
                               value="{{ $organizer->commission_bps !== null ? rtrim(rtrim(number_format($organizer->commission_bps / 100, 2), '0'), '.') : '' }}"
                               placeholder="{{ rtrim(rtrim(number_format((int) setting('platform_commission_bps', 2000) / 100, 2), '0'), '.') }}" class="input-cyber text-sm">
                        <p class="mt-1 text-xs text-slate-500">0% means no platform fee.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">MoMo number</label>
                        <input type="text" name="payout_momo_number" value="{{ $organizer->payout_momo_number }}" class="input-cyber text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Provider</label>
                        <select name="payout_provider" class="input-cyber text-sm">
                            <option value="">—</option>
                            @foreach(config('ticketing.momo.providers') as $key => $label)
                                <option value="{{ $key }}" @selected($organizer->payout_provider === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button class="btn-primary w-full rounded-xl px-4 py-2.5 text-sm">Save</button>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Account name</label>
                        <input type="text" name="payout_name" value="{{ $organizer->payout_name }}" class="input-cyber text-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Audit notes</label>
                        <input type="text" name="notes" value="{{ $organizer->notes }}" class="input-cyber text-sm">
                    </div>
                </form>
            </div>
        @empty
            <div class="panel p-12 text-center">
                <p class="font-display text-xl font-bold">No organizers yet</p>
                <p class="mt-2 text-sm text-slate-500">Organizer applications appear here for review.</p>
            </div>
        @endforelse
    </div>
</section>
@endsection
