<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Support\Audit;
use App\Support\TicketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PayoutSettingsController extends Controller
{
    use OrganizerScope;

    public function edit(Request $request): View
    {
        $organizer = $this->organizer();

        if ($organizer === null) {
            return redirect()->route('organizer.dashboard')
                ->with('error', 'Complete your organizer profile before requesting payouts.');
        }

        return view('organizer.payout-settings', [
            'organizer' => $organizer,
        ]);
    }

    /**
     * Save the Mobile Money account that receives payouts and the optional
     * social handles shown on the organizer's public listings.
     */
    public function update(Request $request): RedirectResponse
    {
        $organizer = $this->organizer();

        if ($organizer === null) {
            return redirect()->route('organizer.dashboard')
                ->with('error', 'Complete your organizer profile before requesting payouts.');
        }

        $validated = $request->validate([
            'payout_name' => ['required', 'string', 'max:120'],
            'payout_provider' => ['required', Rule::in(array_keys(config('ticketing.momo.providers', ['mtn' => 'MTN', 'airtel' => 'Airtel'])))],
            'payout_momo_number' => ['required', 'string', 'max:30', 'regex:'.config('ticketing.momo.provider_regex', '/^(\+?256|0)\d{9}$/')],
            'instagram' => ['nullable', 'string', 'max:190'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'facebook' => ['nullable', 'string', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
        ]);

        $changedNumber = $organizer->payout_momo_number !== $validated['payout_momo_number']
            || $organizer->payout_provider !== $validated['payout_provider'];

        $organizer->fill([
            'payout_name' => $validated['payout_name'],
            'payout_provider' => $validated['payout_provider'],
            'payout_momo_number' => $validated['payout_momo_number'],
            'instagram' => $validated['instagram'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'facebook' => $validated['facebook'] ?? null,
            'website' => $validated['website'] ?? null,
            'payout_submitted_at' => now(),
        ]);

        if ($changedNumber) {
            // A new receiving number has to be re-verified by the platform
            // before any share of the sales is settled to it.
            $organizer->payout_approved_at = null;
        }

        $organizer->save();

        Audit::log('organizer.payout_submitted', $organizer, [
            'provider' => $organizer->payout_provider,
            'reapproval_required' => $changedNumber,
        ]);

        TicketingNotifier::admin(
            'Organizer payout account needs approval',
            $organizer->business_name.' submitted or changed a Mobile Money payout account.',
            ['label' => 'Review payout details', 'url' => route('admin.approvals.index')],
        );
        TicketingNotifier::user(
            $organizer->user,
            'Payout account submitted',
            'Your payout details were submitted. They must be verified before payouts can be made.',
        );

        return redirect()->route('organizer.payout-settings.edit')
            ->with('status', 'Payout details submitted. The platform will approve your Mobile Money account shortly.');
    }
}
