@extends('layouts.app')

@section('title', 'Admin — Novara Tickets')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 space-y-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="pill pill-pulse">Admin</span>
                <span class="text-xs text-slate-500">{{ now()->format('D d M Y · H:i') }} EAT</span>
            </div>
            <h1 class="font-display text-2xl font-black text-slate-100 sm:text-3xl">Control Room</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-outline rounded-lg px-4 py-2 text-sm">Users</a>
            <a href="{{ route('admin.analytics.index') }}" class="btn-outline rounded-lg px-4 py-2 text-sm">Analytics</a>
            <a href="{{ route('admin.approvals.index') }}" class="btn-outline rounded-lg px-4 py-2 text-sm">Approvals</a>
            <a href="{{ route('admin.ledger') }}" class="btn-outline rounded-lg px-4 py-2 text-sm">Ledger</a>
        </div>
    </div>

    {{-- Revenue stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Gross volume',      \App\Support\Money::format($stats['volume_minor']),     'text-neon',  'Total paid orders'],
            ['Platform revenue',  \App\Support\Money::format($stats['commission_minor']), 'text-pulse', 'Your commission cut'],
            ['Organizer net',     \App\Support\Money::format($stats['net_minor']),        'text-acid',  'After platform fee'],
            ['Tickets issued',    number_format($stats['tickets_sold']),                  'text-amber', 'Non-refunded'],
        ] as [$label, $value, $tone, $sub])
            <div class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-black {{ $tone }}">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    {{-- Secondary stats --}}
    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        @foreach([
            ['Paid orders',       $stats['orders']],
            ['Pending orders',    $stats['pending_orders']],
            ['Total organizers',  $stats['organizers']],
            ['Awaiting approval', $stats['pending_organizers']],
        ] as [$label, $value])
            <div class="panel px-5 py-4">
                <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold">{{ $label }}</p>
                <p class="mt-1.5 font-display text-xl font-bold text-slate-100">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Chart + Commission --}}
    <div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">

        {{-- 14-day chart --}}
        <div class="panel p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Revenue</p>
                    <h2 class="font-display text-lg font-bold text-slate-100 mt-0.5">Last 14 days</h2>
                </div>
                <span class="text-xs text-slate-500">Fee {{ \App\Support\Money::percent($commissionBps) }}</span>
            </div>
            <div class="flex h-48 items-end gap-1.5">
                @foreach($series as $point)
                    @php $h = max(2, (int) round(($point['gross'] / $maxGross) * 100)); @endphp
                    <div class="group flex flex-1 flex-col items-center gap-1.5">
                        <span class="text-[.6rem] text-slate-500 opacity-0 group-hover:opacity-100 transition whitespace-nowrap">
                            {{ \App\Support\Money::format($point['gross']) }}
                        </span>
                        <div class="w-full rounded-t bg-gradient-to-t from-neon/40 to-neon group-hover:from-pulse/50 group-hover:to-pulse transition-all duration-200"
                             style="height:{{ $h }}%"></div>
                        <span class="text-[.6rem] text-slate-600 rotate-0">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Commission control --}}
        <div class="panel p-6 flex flex-col">
            <div class="mb-5">
                <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Settings</p>
                <h2 class="font-display text-lg font-bold text-slate-100 mt-0.5">Platform commission</h2>
            </div>

            <form method="POST" action="{{ route('admin.settings.commission') }}" class="space-y-3 flex-1">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Commission %</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_percent"
                           value="{{ rtrim(rtrim(number_format($commissionBps / 100, 2), '0'), '.') }}"
                           required class="input-cyber">
                </div>
                <button class="btn-primary w-full rounded-xl py-2.5 text-sm">Save</button>
            </form>

            <div class="mt-5 pt-5 border-t border-edge/60">
                <p class="text-xs text-slate-500 mb-3">Quick set</p>
                <div class="flex flex-wrap gap-2">
                    @foreach([5, 10, 15, 20] as $pct)
                        <form method="POST" action="{{ route('admin.settings.commission') }}">
                            @csrf
                            <input type="hidden" name="commission_percent" value="{{ $pct }}">
                            <button class="btn-outline rounded-lg px-3 py-1.5 text-xs">{{ $pct }}%</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Approval queue + Moderation queue --}}
    <div class="grid gap-5 lg:grid-cols-2">

        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-edge/60">
                <h2 class="font-display font-bold text-slate-100">Merchant approvals</h2>
                <a href="{{ route('admin.approvals.index') }}" class="text-xs text-neon hover:text-acid transition">Manage all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[480px]">
                    <thead><tr><th>Business</th><th>Owner</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($pendingOrganizers as $organizer)
                            <tr>
                                <td class="text-slate-200 font-medium">{{ $organizer->business_name }}</td>
                                <td class="text-slate-400 text-xs">{{ $organizer->user?->email }}</td>
                                <td><span class="pill pill-pending">pending</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.approvals.update', $organizer) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button class="text-xs text-acid hover:underline font-medium">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-600">No pending merchants.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-edge/60">
                <h2 class="font-display font-bold text-slate-100">Event moderation</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-xs text-neon hover:text-acid transition">Orders →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[480px]">
                    <thead><tr><th>Preview</th><th>Listing</th><th>Organizer</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($pendingListings as $listing)
                            <tr>
                                <td>
                                    @if($listing->coverImageUrl())
                                        <img src="{{ $listing->coverImageUrl() }}" alt="{{ $listing->title }} banner" class="h-12 w-20 rounded-md object-cover">
                                    @else
                                        <span class="text-xs text-slate-500">No banner</span>
                                    @endif
                                </td>
                                <td class="text-slate-200 font-medium">{{ $listing->title }}</td>
                                <td class="text-slate-400 text-xs">{{ $listing->organizer?->business_name }}</td>
                                <td><span class="pill pill-pending">pending</span></td>
                                <td>
                                    <a href="{{ route('admin.listings.review', $listing) }}" class="text-xs text-neon hover:underline font-medium">Review →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-sm text-slate-600">Nothing waiting for review.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Featured curation --}}
    <div class="panel overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-edge/60">
            <div>
                <h2 class="font-display font-bold text-slate-100">Our Picks curation</h2>
                <p class="text-xs text-slate-500 mt-0.5">Featured listings lead the homepage. Falls back to soonest events when fewer than 4 are featured.</p>
            </div>
            <span class="pill pill-info">{{ $featuredListings->where('featured')->count() }}/4 featured</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table w-full min-w-[560px]">
                <thead><tr><th>Listing</th><th>Organizer</th><th>Date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($featuredListings as $listing)
                        <tr>
                            <td class="text-slate-200 font-medium">{{ $listing->title }}</td>
                            <td class="text-slate-400 text-xs">{{ $listing->organizer?->business_name }}</td>
                            <td class="text-slate-500 text-xs">{{ $listing->starts_at->format('d M Y') }}</td>
                            <td>
                                <span class="pill {{ $listing->featured ? 'pill-info' : 'pill-pending' }}">
                                    {{ $listing->status }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.listings.feature', $listing) }}">
                                    @csrf
                                    <button class="text-xs font-medium {{ $listing->featured ? 'text-slate-500 hover:text-pulse' : 'text-neon hover:underline' }} transition">
                                        {{ $listing->featured ? 'Unfeature' : 'Feature' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-slate-600">No published listings to curate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent orders + Ledger --}}
    <div class="grid gap-5 lg:grid-cols-2">

        <div class="panel overflow-hidden">
            <div class="px-5 py-4 border-b border-edge/60">
                <h2 class="font-display font-bold text-slate-100">Recent orders</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[440px]">
                    <thead><tr><th>Reference</th><th>Buyer</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="font-mono text-xs text-slate-400">{{ $order->tx_ref }}</td>
                                <td class="text-slate-300 text-sm">{{ $order->buyer_name }}</td>
                                <td class="font-display font-bold text-neon text-sm">@money($order->total_minor)</td>
                                <td>
                                    <span class="pill {{ $order->status === 'paid' ? 'pill-paid' : 'pill-pending' }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-600">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="px-5 py-4 border-b border-edge/60">
                <h2 class="font-display font-bold text-slate-100">Latest ledger</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[440px]">
                    <thead><tr><th>Type</th><th>Memo</th><th>Amount</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse($recentLedger as $tx)
                            <tr>
                                <td>
                                    <span class="pill {{ $tx->type === 'fee' ? 'pill-pending' : 'pill-info' }}">{{ $tx->type }}</span>
                                </td>
                                <td class="text-slate-400 text-xs max-w-[140px] truncate">{{ $tx->memo }}</td>
                                <td class="font-display font-bold text-sm {{ $tx->amount_minor >= 0 ? 'text-acid' : 'text-red-400' }}">
                                    @money(abs($tx->amount_minor))
                                </td>
                                <td class="text-xs text-slate-600">{{ $tx->created_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-600">Ledger is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
