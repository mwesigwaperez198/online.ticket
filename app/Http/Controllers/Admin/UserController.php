<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('organizer')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.users', ['users' => $users]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'role' => ['required', 'in:buyer,organizer,admin'],
        ]);

        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own admin role.');
        }

        $user->forceFill([
            'status' => $validated['status'],
            'role' => $validated['role'],
        ])->save();

        Audit::log('user.moderated', $user, $validated);

        return back()->with('status', 'User updated.');
    }
}
