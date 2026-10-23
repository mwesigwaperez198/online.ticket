<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\Audit;
use App\Support\TicketingNotifier;
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
            'published_at' => in_array($validated['status'], ['published', 'live'], true) ? ($listing->published_at ?? now()) : $listing->published_at,
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

        $notice = match ($validated['status']) {
            'published', 'live' => ['Event approved', 'Your listing "'.$listing->title.'" is approved and visible to buyers.'],
            'draft' => ['Changes requested for your event', 'An administrator returned "'.$listing->title.'" for changes. Please review your organizer dashboard.'],
            'cancelled' => ['Event cancelled', 'Your listing "'.$listing->title.'" was cancelled by the platform. Contact support if you need help.'],
            default => ['Listing review update', 'The status of "'.$listing->title.'" is now '.$validated['status'].'.'],
        };
        TicketingNotifier::user($listing->organizer?->user, $notice[0], $notice[1], [
            'label' => 'Organizer dashboard',
            'url' => route('organizer.listings.index'),
        ]);

        if ($validated['status'] === 'cancelled') {
            $listing->orders()->where('status', 'paid')->with('user')->get()->each(function ($order) use ($listing): void {
                TicketingNotifier::user(
                    $order->user,
                    'Event cancelled — '.$listing->title,
                    'The event you purchased tickets for was cancelled by the platform. Contact support with order reference '.$order->tx_ref.' for assistance with your refund.',
                    ['label' => 'Open order', 'url' => route('buyer.orders.show', $order)],
                );
            });
        }

        return back()->with('status', 'Listing status updated to '.$validated['status'].'.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $listing->forceFill(['status' => 'cancelled'])->save();
        $listing->delete();

        Audit::log('listing.archived', $listing, ['title' => $listing->title]);
        TicketingNotifier::user(
            $listing->organizer?->user,
            'Your event was cancelled',
            'An administrator archived "'.$listing->title.'". Orders and financial history have been retained.',
        );

        $listing->orders()->where('status', 'paid')->with('user')->get()->each(function ($order) use ($listing): void {
            TicketingNotifier::user(
                $order->user,
                'Event cancelled — '.$listing->title,
                'The event you purchased tickets for was cancelled by the platform. Contact support with order reference '.$order->tx_ref.' for assistance with your refund.',
                ['label' => 'Open order', 'url' => route('buyer.orders.show', $order)],
            );
        });

        return redirect()->route('admin.dashboard')->with('status', 'Listing archived from the marketplace. Order and payment records were retained.');
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
