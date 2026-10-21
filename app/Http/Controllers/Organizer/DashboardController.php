<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    use OrganizerScope;

    public function index(Request $request): View
    {
        $organizer = $this->organizer();

        $ticketsSold = (int) Ticket::query()
            ->whereIn('listing_id', $this->scopedListings()->select('id'))
            ->where('status', '!=', 'refunded')
            ->count();

        $gross = (int) $this->scopedOrders()->where('status', 'paid')->sum('total_minor');
        $commission = (int) $this->scopedOrders()->where('status', 'paid')->sum('commission_minor');
        $net = $gross - $commission;

        $wallet = $organizer?->wallet()->first();

        $capacityTotal = (int) TicketType::query()
            ->whereIn('listing_id', $this->scopedListings()->select('id'))
            ->sum('capacity');

        $capacityRemaining = (int) TicketType::query()
            ->whereIn('listing_id', $this->scopedListings()->select('id'))
            ->sum(DB::raw('capacity - sold_count - held_count'));

        $recentOrders = $this->scopedOrders()
            ->with(['listing', 'user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $checkIns = Ticket::query()
            ->whereIn('listing_id', $this->scopedListings()->select('id'))
            ->whereNotNull('used_at')
            ->with(['listing'])
            ->orderByDesc('used_at')
            ->limit(10)
            ->get();

        return view('organizer.dashboard', [
            'organizer' => $organizer,
            'metrics' => [
                'tickets_sold' => $ticketsSold,
                'gross_minor' => $gross,
                'commission_minor' => $commission,
                'net_minor' => $net,
                'balance_minor' => (int) ($wallet->balance_minor ?? 0),
                'capacity_total' => $capacityTotal,
                'capacity_remaining' => $capacityRemaining,
                'orders' => (int) $this->scopedOrders()->where('status', 'paid')->count(),
            ],
            'recentOrders' => $recentOrders,
            'checkIns' => $checkIns,
        ]);
    }
}
