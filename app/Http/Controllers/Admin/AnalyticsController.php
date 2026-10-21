<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $roleCounts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $signups = User::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $logins = User::query()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->selectRaw('date(last_login_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return view('admin.analytics', [
            'roleCounts' => [
                'buyer' => (int) ($roleCounts['buyer'] ?? 0),
                'organizer' => (int) ($roleCounts['organizer'] ?? 0),
                'admin' => (int) ($roleCounts['admin'] ?? 0),
            ],
            'totalUsers' => (int) $roleCounts->sum(),
            'activeOrganizers' => Organizer::query()->where('status', 'approved')->count(),
            'pendingApprovals' => Organizer::query()->where('status', '!=', 'approved')->count(),
            'publishedListings' => Listing::query()->published()->count(),
            'paidOrders' => Order::query()->where('status', 'paid')->count(),
            'grossVolume' => (int) Order::query()->where('status', 'paid')->sum('total_minor'),
            'signups' => $signups,
            'logins' => $logins,
            'recentActivity' => AuditLog::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
