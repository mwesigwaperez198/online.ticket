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
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payout_momo_number' => ['nullable', 'string', 'max:30'],
            'payout_provider' => ['nullable', 'string', 'max:30'],
            'payout_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $commissionBps = $organizer->commission_bps;

        if ($validated['commission_percent'] !== null) {
            $commissionBps = (int) round(((float) $validated['commission_percent']) * 100);
        }

        $payoutNumber = $validated['payout_momo_number'] ?? $organizer->payout_momo_number;

        $organizer->fill([
            'status' => $validated['status'],
            'commission_bps' => $commissionBps,
            'payout_momo_number' => $payoutNumber,
            'payout_provider' => $validated['payout_provider'] ?? $organizer->payout_provider,
            'payout_name' => $validated['payout_name'] ?? $organizer->payout_name,
            'notes' => $validated['notes'] ?? $organizer->notes,
        ]);

        if ($validated['status'] === 'approved' && $organizer->approved_at === null) {
            $organizer->approved_at = now();
        }

        if ($validated['status'] === 'approved' && filled($payoutNumber)) {
            $organizer->payout_approved_at = now();
        }

        $organizer->save();

        $organizer->user?->forceFill([
            'role' => $validated['status'] === 'approved' ? 'organizer' : $organizer->user->role,
        ])->save();

        Audit::log('organizer.moderated', $organizer, [
            'status' => $validated['status'],
            'commission_bps' => $commissionBps,
        ]);

        return back()->with('status', 'Organizer updated.');
    }
}
