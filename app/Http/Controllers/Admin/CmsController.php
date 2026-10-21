<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CmsController extends Controller
{
    public function index(Request $request): View
    {
        $volume = (int) Order::query()->where('status', 'paid')->sum('total_minor');
        $commission = (int) Order::query()->where('status', 'paid')->sum('commission_minor');
        $ticketsSold = (int) DB::table('tickets')->where('status', '!=', 'refunded')->count();

        $daily = Order::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(paid_at) as day, SUM(total_minor) as gross, COUNT(*) as orders')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $row = $daily->get($day);
            $series[] = [
                'label' => now()->subDays($i)->format('d M'),
                'gross' => (int) ($row->gross ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        $maxGross = max(1, max(array_map(static fn (array $row): int => $row['gross'], $series)));

        return view('admin.cms', [
            'stats' => [
                'volume_minor' => $volume,
                'commission_minor' => $commission,
                'net_minor' => $volume - $commission,
                'tickets_sold' => $ticketsSold,
                'orders' => (int) Order::query()->where('status', 'paid')->count(),
                'pending_orders' => (int) Order::query()->where('status', 'pending')->count(),
                'organizers' => (int) Organizer::query()->count(),
                'approved_organizers' => (int) Organizer::query()->where('status', 'approved')->count(),
                'pending_organizers' => (int) Organizer::query()->where('status', 'pending')->count(),
                'users' => (int) User::query()->count(),
            ],
            'series' => $series,
            'maxGross' => $maxGross,
            'pendingOrganizers' => Organizer::query()->with('user')->where('status', 'pending')->limit(8)->get(),
            'pendingListings' => Listing::query()->with('organizer')->where('status', 'pending')->limit(8)->get(),
            'featuredListings' => Listing::query()->with('organizer')
                ->whereIn('status', ['published', 'live'])
                ->orderByDesc('featured')
                ->orderByDesc('starts_at')
                ->limit(12)
                ->get(),
            'recentOrders' => Order::query()->with(['listing', 'user'])->orderByDesc('created_at')->limit(10)->get(),
            'recentLedger' => WalletTransaction::query()->with('organizer')->orderByDesc('created_at')->limit(10)->get(),
            'commissionBps' => (int) setting('platform_commission_bps', 2000),
        ]);
    }
}
