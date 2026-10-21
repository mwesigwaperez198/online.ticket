<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Models\Listing;
use App\Models\Order;
use App\Models\Organizer;
use Illuminate\Database\Eloquent\Builder;

trait OrganizerScope
{
    protected function organizer(): ?Organizer
    {
        return Organizer::query()->where('user_id', auth()->id())->first();
    }

    /**
     * Listings that belong to the signed-in organizer only.
     */
    protected function scopedListings(): Builder
    {
        $organizerId = $this->organizer()?->id ?? 0;

        return Listing::query()->where('organizer_id', $organizerId);
    }

    protected function scopedOrders(): Builder
    {
        return Order::query()->whereIn('listing_id', $this->scopedListings()->select('id'));
    }
}
