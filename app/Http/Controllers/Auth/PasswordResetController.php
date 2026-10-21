<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link. The response never reveals whether the address is
     * registered so the form cannot be used to enumerate accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        Audit::log('auth.password_reset_requested', null, ['email' => $request->string('email')->toString()]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('status', 'If that email is registered we have sent a reset link.');
        }

        return back()->with('status', 'If that email is registered we have sent a reset link.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                Audit::log('auth.password_reset', $user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->with('error', 'That reset link is invalid or has expired. Request a new one.');
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with the new password.');
    }
}
