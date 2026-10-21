@extends('layouts.app')

@section('title', 'Admin CMS — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="pill pill-pending">Admin console</span>
            <h1 class="mt-3 font-display text-3xl font-black">Platform control room</h1>
            <p class="mt-1 text-sm text-slate-500">Transaction volume, merchant audit and moderation queues.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.analytics.index') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">User analytics</a>
            <a href="{{ route('admin.approvals.index') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">Approvals</a>
            <a href="{{ route('admin.ledger') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">Ledger</a>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Gross volume', \App\Support\Money::format($stats['volume_minor']), 'text-neon'],
            ['Platform revenue', \App\Support\Money::format($stats['commission_minor']), 'text-pulse'],
            ['Organizer net', \App\Support\Money::format($stats['net_minor']), 'text-acid'],
            ['Tickets issued', number_format($stats['tickets_sold']), 'text-slate-100'],
        ] as [$label, $value, $tone])
            <div class="panel px-5 py-4">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-black {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        @foreach([
            ['Paid orders', $stats['orders']],
            ['Pending orders', $stats['pending_orders']],
            ['Organizers', $stats['organizers']],
            ['Pending approval', $stats['pending_organizers']],
        ] as [$label, $value])
            <div class="panel px-5 py-3">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-1 font-display text-lg font-bold text-slate-100">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.6fr_1fr]">
        <div class="panel p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Transaction volume — last 14 days</h2>
                <span class="text-xs text-slate-500">Current fee {{ \App\Support\Money::percent($commissionBps) }}</span>
            </div>

            <div class="mt-6 flex h-56 items-end gap-2">
                @foreach($series as $point)
                    @php
                        $height = (int) round(($point['gross'] / $maxGross) * 100);
                        $height = max(2, $height);
                    @endphp
                    <div class="group flex flex-1 flex-col items-center gap-2">
                        <span class="text-[.6rem] text-slate-500 opacity-0 transition group-hover:opacity-100">
                            {{ \App\Support\Money::format($point['gross']) }}
                        </span>
                        <div class="w-full rounded-t-md bg-gradient-to-t from-neon/30 to-neon transition group-hover:from-pulse/40 group-hover:to-pulse"
                             style="height: {{ $height }}%"></div>
                        <span class="text-[.6rem] text-slate-600">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel p-6">
            <h2 class="font-display text-lg font-bold">Platform commission</h2>
            <p class="mt-1 text-xs text-slate-500">Always prompted before it is applied to new sales.</p>

            <form method="POST" action="{{ route('admin.settings.commission') }}" class="mt-5 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Commission percentage</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ rtrim(rtrim(number_format($commissionBps / 100, 2), '0'), '.') }}" required class="input-cyber text-sm">
                </div>
                <button class="btn-neon w-full rounded-xl px-4 py-3">Save commission</button>
            </form>

            <div class="mt-6 border-t border-edge/70 pt-5">
                <h3 class="text-xs uppercase tracking-wider text-slate-500">Quick set</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach([5, 10, 15, 20] as $percent)
                        <form method="POST" action="{{ route('admin.settings.commission') }}">
                            @csrf
                            <input type="hidden" name="commission_percent" value="{{ $percent }}">
                            <button class="btn-ghost rounded-lg px-3 py-1.5 text-xs">{{ $percent }}%</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
                <h2 class="font-display text-lg font-bold">Merchant approval queue</h2>
                <a href="{{ route('admin.approvals.index') }}" class="text-xs uppercase tracking-wider text-neon hover:underline">Manage</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[520px]">
                    <thead><tr><th>Business</th><th>Owner</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($pendingOrganizers as $organizer)
                            <tr>
                                <td class="text-slate-200">{{ $organizer->business_name }}</td>
                                <td class="text-slate-400">{{ $organizer->user?->email }}</td>
                                <td><span class="pill pill-pending">{{ $organizer->status }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.approvals.update', $organizer) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button class="text-xs uppercase tracking-wider text-acid hover:underline">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">No merchants awaiting review.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
                <h2 class="font-display text-lg font-bold">Event moderation queue</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-xs uppercase tracking-wider text-neon hover:underline">Orders</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[520px]">
                    <thead><tr><th>Listing</th><th>Organizer</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($pendingListings as $listing)
                            <tr>
                                <td class="text-slate-200">{{ $listing->title }}</td>
                                <td class="text-slate-400">{{ $listing->organizer?->business_name }}</td>
                                <td><span class="pill pill-pending">{{ $listing->status }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.listings.moderate', $listing) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="published">
                                        <button class="text-xs uppercase tracking-wider text-acid hover:underline">Publish</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">Nothing waiting for moderation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-8 panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
            <div>
                <h2 class="font-display text-lg font-bold">Our Picks curation</h2>
                <p class="mt-0.5 text-xs text-slate-500">Featured listings lead the home page. The row falls back to the soonest events when fewer than four are featured.</p>
            </div>
            <span class="pill pill-info">{{ $featuredListings->where('featured')->count() }}/4 featured</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table w-full min-w-[520px]">
                <thead><tr><th>Listing</th><th>Organizer</th><th>When</th><th>Status</th><th>Pick</th></tr></thead>
                <tbody>
                    @forelse($featuredListings as $listing)
                        <tr>
                            <td class="text-slate-200">{{ $listing->title }}</td>
                            <td class="text-slate-400">{{ $listing->organizer?->business_name }}</td>
                            <td class="text-slate-400">{{ $listing->starts_at->format('d M Y') }}</td>
                            <td><span class="pill {{ $listing->featured ? 'pill-info' : 'pill-pending' }}">{{ $listing->status }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.listings.feature', $listing) }}">
                                    @csrf
                                    <button class="text-xs uppercase tracking-wider {{ $listing->featured ? 'text-slate-500 hover:text-pulse' : 'text-neon hover:underline' }}">
                                        {{ $listing->featured ? 'Remove' : 'Feature' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-slate-500">No published listings to curate yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="panel overflow-hidden">
            <h2 class="border-b border-edge/70 px-5 py-4 font-display text-lg font-bold">Recent orders</h2>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[520px]">
                    <thead><tr><th>Reference</th><th>Buyer</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="font-mono text-xs text-slate-300">{{ $order->tx_ref }}</td>
                                <td class="text-slate-300">{{ $order->buyer_name }}</td>
                                <td class="text-slate-200">@money($order->total_minor)</td>
                                <td><span class="pill {{ $order->status === 'paid' ? 'pill-paid' : 'pill-pending' }}">{{ $order->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <h2 class="border-b border-edge/70 px-5 py-4 font-display text-lg font-bold">Latest ledger movement</h2>
            <div class="overflow-x-auto">
                <table class="data-table w-full min-w-[520px]">
                    <thead><tr><th>Type</th><th>Memo</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($recentLedger as $tx)
                            <tr>
                                <td><span class="pill {{ $tx->type === 'fee' ? 'pill-pending' : 'pill-info' }}">{{ $tx->type }}</span></td>
                                <td class="text-slate-300">{{ $tx->memo }}</td>
                                <td class="{{ $tx->amount_minor >= 0 ? 'text-acid' : 'text-red-300' }}">@money(abs($tx->amount_minor))</td>
                                <td class="text-xs text-slate-500">{{ $tx->created_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-sm text-slate-500">Ledger is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
