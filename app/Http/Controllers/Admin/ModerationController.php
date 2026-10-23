<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ModerationController extends Controller
{
    public function show(Listing $listing): View
    {
        return view('admin.listing-review', [
            'listing' => $listing->load(['organizer.user', 'ticketTypes', 'services', 'images']),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,pending,published,live,ended,cancelled'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $listing->forceFill([
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? ($listing->published_at ?? now()) : $listing->published_at,
        ])->save();

        if (($validated['commission_percent'] ?? null) !== null) {
            $organizer = $listing->organizer;
            $commissionBps = (int) round(((float) $validated['commission_percent']) * 100);
            $organizer->forceFill(['commission_bps' => $commissionBps])->save();

            Audit::log('organizer.commission_updated', $organizer, [
                'commission_bps' => $commissionBps,
                'listing_id' => $listing->id,
            ]);
        }

        Audit::log('listing.moderated', $listing, ['status' => $validated['status']]);

        return back()->with('status', 'Listing status updated to '.$validated['status'].'.');
    }

    /**
     * Toggle a published listing in and out of the curated "Our Picks" row.
     */
    public function feature(Listing $listing): RedirectResponse
    {
        $listing->forceFill(['featured' => ! $listing->featured])->save();

        Audit::log('listing.featured_toggled', $listing, ['featured' => $listing->featured]);

        return back()->with('status', $listing->featured
            ? $listing->title.' now appears in Our Picks.'
            : $listing->title.' was removed from Our Picks.');
    }
}
