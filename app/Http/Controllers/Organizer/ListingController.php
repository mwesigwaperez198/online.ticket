<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\TicketType;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ListingController extends Controller
{
    use OrganizerScope;

    public function index(): View
    {
        return view('organizer.listings.index', [
            'listings' => $this->scopedListings()->with('ticketTypes')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizer.listings.form', [
            'listing' => new Listing(['type' => 'event', 'max_per_order' => 10, 'refund_window_hours' => 72]),
            'ticketTypes' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizer = $this->organizer();

        if ($organizer === null) {
            return back()->with('error', 'Organizer profile missing. Contact support.');
        }

        $validated = $request->validate([
            'type' => ['required', 'in:event,service'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'refund_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'ticket_types' => ['nullable', 'array'],
            'ticket_types.*.name' => ['required_with:ticket_types', 'string', 'max:80'],
            'ticket_types.*.price' => ['required_with:ticket_types', 'numeric', 'min:0'],
            'ticket_types.*.capacity' => ['required_with:ticket_types', 'integer', 'min:1'],
        ]);

        $listing = DB::transaction(function () use ($validated, $organizer): Listing {
            $listing = Listing::query()->create([
                'organizer_id' => $organizer->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'venue' => $validated['venue'] ?? null,
                'city' => $validated['city'] ?? null,
                'region' => $validated['region'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => 'draft',
                'max_per_order' => (int) $validated['max_per_order'],
                'refund_window_hours' => (int) $validated['refund_window_hours'],
                'allow_refunds' => true,
            ]);

            foreach ((array) ($validated['ticket_types'] ?? []) as $index => $type) {
                if (blank($type['name'] ?? null)) {
                    continue;
                }

                TicketType::query()->create([
                    'listing_id' => $listing->id,
                    'name' => $type['name'],
                    'description' => $type['description'] ?? null,
                    'price_minor' => (int) round(((float) $type['price']) * 100),
                    'currency' => 'GHS',
                    'capacity' => (int) $type['capacity'],
                    'sort_order' => $index,
                    'active' => true,
                ]);
            }

            return $listing;
        });

        Audit::log('listing.created', $listing, ['type' => $listing->type]);

        return redirect()->route('organizer.listings.edit', $listing)
            ->with('status', 'Listing saved as draft. Submit it for approval when ready.');
    }

    public function edit(Listing $listing): View|RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        return view('organizer.listings.form', [
            'listing' => $listing,
            'ticketTypes' => $listing->ticketTypes()->get(),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'refund_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
        ]);

        $listing->fill($validated)->save();

        Audit::log('listing.updated', $listing);

        return back()->with('status', 'Listing updated.');
    }

    public function publish(Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        if ($listing->ticketTypes()->count() === 0 && $listing->isEvent()) {
            return back()->with('error', 'Add at least one ticket tier before submitting.');
        }

        $listing->forceFill(['status' => 'pending'])->save();

        Audit::log('listing.submitted', $listing);

        return back()->with('status', 'Submitted for approval.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        if ($listing->orders()->whereIn('status', ['paid', 'pending'])->exists()) {
            return back()->with('error', 'This listing has live orders and cannot be deleted.');
        }

        $listing->delete();

        Audit::log('listing.deleted', $listing);

        return redirect()->route('organizer.listings.index')->with('status', 'Listing deleted.');
    }

    private function owns(Listing $listing): bool
    {
        return $listing->organizer_id === ($this->organizer()?->id ?? 0);
    }
}
