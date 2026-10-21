<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\TicketType;
use App\Services\HoldExpiryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class MarketplaceController extends Controller
{
    /**
     * Listings per page in the "All Events" grid.
     */
    private const PER_PAGE = 8;

    /**
     * Curated cards in the "Our Picks" row.
     */
    private const PICKS = 4;

    public function index(Request $request, HoldExpiryService $sweep): View
    {
        $this->sweepExpiredHolds($sweep);

        $listings = $this->paginatePublished($request);

        return view('welcome', [
            'picks' => $this->featuredPicks($request),
            'listings' => $listings,
            'search' => $request->string('q')->toString(),
            'type' => $request->string('type')->toString(),
        ]);
    }

    /**
     * The next page of the "All Events" grid, rendered server side.
     *
     * The Blade card partial is the single source of truth for markup, so a
     * page fetched here is pixel-identical to the first page the visitor
     * received. Only the rendered HTML and the next cursor travel over the
     * wire; no listing internals are exposed.
     */
    public function loadMore(Request $request, HoldExpiryService $sweep): JsonResponse
    {
        $this->sweepExpiredHolds($sweep);

        $page = max(1, $request->integer('page', 1));
        $listings = $this->paginatePublished($request, $page);

        $html = collect($listings->items())
            ->map(fn (Listing $listing): string => view('partials.listing-card', [
                'listing' => $listing,
                'featured' => false,
            ])->render())
            ->implode("\n");

        return response()->json([
            'html' => $html,
            'next' => $listings->hasMorePages() ? $listings->currentPage() + 1 : null,
            'page' => $page,
            'last' => ! $listings->hasMorePages(),
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
            'commissionBps' => $listing->organizer?->effectiveCommissionBps() ?? (int) setting('platform_commission_bps', 2000),
        ]);
    }

    /**
     * The curated "Our Picks" row: featured listings first, then the soonest
     * published events so the row is never empty on a fresh marketplace.
     *
     * @return Collection<int, Listing>
     */
    private function featuredPicks(Request $request): Collection
    {
        $featured = $this->publishedQuery($request)
            ->featured()
            ->with(['organizer', 'ticketTypes', 'services.slots'])
            ->orderBy('starts_at')
            ->limit(self::PICKS)
            ->get();

        if ($featured->count() >= self::PICKS) {
            return $featured;
        }

        $ids = $featured->pluck('id');

        $filler = $this->publishedQuery($request)
            ->whereNotIn('id', $ids)
            ->with(['organizer', 'ticketTypes', 'services.slots'])
            ->orderBy('starts_at')
            ->limit(self::PICKS - $featured->count())
            ->get();

        return $featured->concat($filler)->values();
    }

    private function paginatePublished(Request $request, ?int $page = null): LengthAwarePaginator
    {
        return $this->publishedQuery($request)
            ->with(['organizer', 'ticketTypes', 'services.slots'])
            ->orderBy('starts_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * The shared published-listing query behind both rows, honouring the
     * type filter and free-text search from the query string.
     */
    private function publishedQuery(Request $request): Builder
    {
        $query = Listing::query()->published();

        if ($type = $request->string('type')->toString()) {
            $query->ofType($type);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('venue', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }
}
