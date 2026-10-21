<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\TicketType;
use App\Services\HoldExpiryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MarketplaceController extends Controller
{
    public function index(Request $request, HoldExpiryService $sweep): View
    {
        $this->sweepExpiredHolds($sweep);

        $query = Listing::query()
            ->published()
            ->with(['organizer', 'ticketTypes', 'services.slots'])
            ->orderBy('starts_at');

        if ($type = $request->string('type')->toString()) {
            $query->ofType($type);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('venue', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%');
            });
        }

        $listings = $query->paginate(12)->withQueryString();

        return view('welcome', [
            'listings' => $listings,
            'search' => $search ?? '',
            'type' => $type ?? '',
        ]);
    }

    public function show(string $slug, HoldExpiryService $sweep): View
    {
        $this->sweepExpiredHolds($sweep);

        $listing = Listing::query()
            ->published()
            ->with(['organizer.user', 'ticketTypes', 'services.slots'])
            ->where('slug', $slug)
            ->firstOrFail();

        $ticketTypes = TicketType::query()
            ->where('listing_id', $listing->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        return view('events.show', [
            'listing' => $listing,
            'ticketTypes' => $ticketTypes,
            'commissionBps' => (int) setting('platform_commission_bps', 2000),
        ]);
    }
}
