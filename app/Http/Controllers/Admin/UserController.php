<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\TicketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::withTrashed()
            ->with('organizer')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.users', ['users' => $users]);
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'role' => ['required', 'in:buyer,organizer,admin'],
        ]);

        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own admin role.');
        }

        if ($user->trashed()) {
            $user->restore();
            if ($user->organizer?->trashed()) {
                $user->organizer->restore();
            }
        }

        $user->forceFill([
            'status' => $validated['status'],
            'role' => $validated['role'],
        ])->save();

        Audit::log('user.moderated', $user, $validated);

        if ($validated['status'] === 'active') {
            TicketingNotifier::user($user, 'Your Novara account is active', 'An administrator has reactivated your account. You can now sign in.');
        }

        return back()->with('status', 'User updated.');
    }

    public function destroy(Request $request, int $userId): RedirectResponse
    {
        $user = User::query()->with('organizer')->findOrFail($userId);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot archive your own administrator account.');
        }

        $user->forceFill(['status' => 'suspended'])->save();
        if ($user->organizer !== null) {
            foreach ($user->organizer->listings as $listing) {
                $meta = $listing->meta ?? [];
                $meta['_account_archive_status'] = $listing->status;
                $listing->forceFill(['status' => 'cancelled', 'meta' => $meta])->save();
                $listing->delete();
            }
        }
        $user->organizer?->delete();
        $user->delete();

        Audit::log('user.archived', $user, ['role' => $user->role]);
        TicketingNotifier::user($user, 'Your Novara account was archived', 'Your account was archived by the platform. Your orders and payment history have been retained. Contact support if you need help.');

        return back()->with('status', 'Account archived. Its orders and financial records remain available to administrators.');
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();
        if ($user->organizer?->trashed()) {
            $user->organizer->restore();
        }

        if ($user->organizer !== null) {
            foreach ($user->organizer->listings()->withTrashed()->get() as $listing) {
                if (! $listing->trashed()) {
                    continue;
                }

                $meta = $listing->meta ?? [];
                $restoreStatus = $meta['_account_archive_status'] ?? 'draft';
                unset($meta['_account_archive_status']);

                $listing->restore();
                $listing->forceFill(['status' => $restoreStatus, 'meta' => $meta])->save();
            }
        }
        $user->forceFill(['status' => 'active'])->save();

        Audit::log('user.restored', $user);
        TicketingNotifier::user($user, 'Your Novara account is active', 'An administrator restored your account. Use the secure password reset link if you need to set a new password.');

        return back()->with('status', 'Account restored and activated.');
    }

    public function sendPasswordReset(int $userId): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($userId);

        if ($user->trashed()) {
            return back()->with('error', 'Restore the account before sending a password reset link.');
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        Audit::log('admin.password_reset_link_sent', $user, ['broker_status' => $status]);

        return back()->with('status', 'A one-time password reset link was sent to '.$user->email.'. No password is exposed to staff.');
    }
}
