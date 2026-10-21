@extends('layouts.app')

@section('title', 'User analytics — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="pill pill-info">Usage data</span>
            <h1 class="mt-3 font-display text-3xl font-black">User analytics</h1>
            <p class="mt-1 text-sm text-slate-500">How many people use Novara, who they are and how they engage with the platform.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn-ghost rounded-xl px-4 py-2.5 text-sm">Back to control room</a>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Total accounts', number_format($totalUsers), 'text-slate-100'],
            ['Buyers', number_format($roleCounts['buyer']), 'text-neon'],
            ['Organizers', number_format($roleCounts['organizer']), 'text-acid'],
            ['Admins', number_format($roleCounts['admin']), 'text-pulse'],
        ] as [$label, $value, $tone])
            <div class="panel px-5 py-4">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-black {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Approved organizers', number_format($activeOrganizers)],
            ['Pending approvals', number_format($pendingApprovals)],
            ['Published listings', number_format($publishedListings)],
            ['Paid orders', number_format($paidOrders)],
        ] as [$label, $value])
            <div class="panel px-5 py-3">
                <p class="text-[.7rem] uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-1 font-display text-lg font-bold text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 panel px-5 py-4">
        <p class="text-[.7rem] uppercase tracking-wider text-slate-500">Gross paid volume</p>
        <p class="mt-2 font-display text-2xl font-black text-neon">@money($grossVolume)</p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        @php
            $signupsSeries = [];
            $loginSeries = [];
            for ($day = 29; $day >= 0; $day--) {
                $key = now()->subDays($day)->toDateString();
                $signupsSeries[] = ['label' => now()->subDays($day)->format('d M'), 'total' => (int) ($signups[$key] ?? 0)];
                $loginSeries[] = ['label' => now()->subDays($day)->format('d M'), 'total' => (int) ($logins[$key] ?? 0)];
            }
            $signupsMax = max(1, max(array_column($signupsSeries, 'total')));
            $loginsMax = max(1, max(array_column($loginSeries, 'total')));
        @endphp

        <div class="panel p-6">
            <h2 class="font-display text-lg font-bold">New accounts — last 30 days</h2>
            <div class="mt-6 flex h-48 items-end gap-1">
                @foreach($signupsSeries as $point)
                    @php $height = max(2, (int) round(($point['total'] / $signupsMax) * 100)); @endphp
                    <div class="group flex flex-1 flex-col items-center gap-1">
                        <span class="text-[.55rem] text-slate-500 opacity-0 transition group-hover:opacity-100">{{ $point['total'] }}</span>
                        <div class="w-full rounded-t-sm bg-gradient-to-t from-neon/30 to-neon transition group-hover:from-pulse/40 group-hover:to-pulse" style="height: {{ $height }}%"></div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-slate-600">{{ $signupsSeries[0]['label'] }} → {{ $signupsSeries[count($signupsSeries) - 1]['label'] }}</p>
        </div>

        <div class="panel p-6">
            <h2 class="font-display text-lg font-bold">Sign-ins — last 30 days</h2>
            <div class="mt-6 flex h-48 items-end gap-1">
                @foreach($loginSeries as $point)
                    @php $height = max(2, (int) round(($point['total'] / $loginsMax) * 100)); @endphp
                    <div class="group flex flex-1 flex-col items-center gap-1">
                        <span class="text-[.55rem] text-slate-500 opacity-0 transition group-hover:opacity-100">{{ $point['total'] }}</span>
                        <div class="w-full rounded-t-sm bg-gradient-to-t from-acid/30 to-acid transition group-hover:from-neon/40 group-hover:to-neon" style="height: {{ $height }}%"></div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-slate-600">{{ $loginSeries[0]['label'] }} → {{ $loginSeries[count($loginSeries) - 1]['label'] }}</p>
        </div>
    </div>

    <div class="mt-8 panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-edge/70 px-5 py-4">
            <h2 class="font-display text-lg font-bold">Recent platform activity</h2>
            <a href="{{ route('admin.users.index') }}" class="text-xs uppercase tracking-wider text-neon hover:underline">All accounts</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table w-full min-w-[560px]">
                <thead><tr><th>Action</th><th>Account</th><th>When</th></tr></thead>
                <tbody>
                    @forelse($recentActivity as $entry)
                        <tr>
                            <td class="text-slate-300">{{ $entry->action }}</td>
                            <td class="text-slate-400">{{ $entry->user?->email ?? 'system' }}</td>
                            <td class="text-xs text-slate-500">{{ $entry->created_at?->format('d M H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-10 text-center text-sm text-slate-500">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
