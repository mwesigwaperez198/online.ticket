<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ModerationController extends Controller
{
    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,pending,published,live,ended,cancelled'],
        ]);

        $listing->forceFill([
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? ($listing->published_at ?? now()) : $listing->published_at,
        ])->save();

        Audit::log('listing.moderated', $listing, ['status' => $validated['status']]);

        return back()->with('status', 'Listing status updated to '.$validated['status'].'.');
    }
}
