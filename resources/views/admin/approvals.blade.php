@extends('layouts.app')

@section('title', 'Merchant approvals — Pulse Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Merchant audit</h1>
    <p class="mt-1 text-sm text-slate-500">Approve organizers, set their commission override and payout details.</p>

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
                    <span class="pill {{ $pill }}">{{ $organizer->status }}</span>
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
                        <input type="number" step="0.01" min="0" max="50" name="commission_bps" value="{{ $organizer->commission_bps !== null ? number_format($organizer->commission_bps / 100, 2) : '' }}" placeholder="Default" class="input-cyber text-sm">
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
                        <button class="btn-neon w-full rounded-xl px-4 py-2.5 text-sm">Save</button>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-5">
                        <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Audit notes</label>
                        <input type="text" name="notes" value="{{ $organizer->notes }}" class="input-cyber text-sm">
                    </div>
                </form>
            </div>
        @empty
            <div class="panel p-12 text-center">
                <p class="font-display text-xl font-bold">No merchants yet</p>
                <p class="mt-2 text-sm text-slate-500">Organizer applications appear here for review.</p>
            </div>
        @endforelse
    </div>
</section>
@endsection
