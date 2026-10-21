<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrganizerApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.approvals', [
            'organizers' => Organizer::query()->with('user')->orderBy('status')->orderByDesc('created_at')->get(),
        ]);
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,suspended'],
            'commission_bps' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'payout_momo_number' => ['nullable', 'string', 'max:30'],
            'payout_provider' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $organizer->fill([
            'status' => $validated['status'],
            'commission_bps' => $validated['commission_bps'] ?? $organizer->commission_bps,
            'payout_momo_number' => $validated['payout_momo_number'] ?? $organizer->payout_momo_number,
            'payout_provider' => $validated['payout_provider'] ?? $organizer->payout_provider,
            'notes' => $validated['notes'] ?? $organizer->notes,
        ]);

        if ($validated['status'] === 'approved' && $organizer->approved_at === null) {
            $organizer->approved_at = now();
        }

        $organizer->save();

        $organizer->user?->forceFill([
            'role' => $validated['status'] === 'approved' ? 'organizer' : $organizer->user->role,
        ])->save();

        Audit::log('organizer.moderated', $organizer, ['status' => $validated['status']]);

        return back()->with('status', 'Organizer updated.');
    }
}
