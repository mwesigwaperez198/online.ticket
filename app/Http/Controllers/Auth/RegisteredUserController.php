<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\Organizer;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RegisteredUserController
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'business_name' => ['nullable', 'string', 'max:160'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role' => 'buyer',
            'status' => 'active',
        ]);

        if (filled($validated['business_name'] ?? null)) {
            Organizer::query()->create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'slug' => $this->uniqueSlug($validated['business_name']),
                'status' => 'pending',
            ]);

            $user->forceFill(['role' => 'organizer'])->save();
        }

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        Audit::log('auth.registered', $user);

        return redirect()->route($user->role === 'organizer' ? 'organizer.dashboard' : 'home')
            ->with('status', 'Account created. Welcome to Pulse Tickets.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organizer';
        $slug = $base;
        $i = 2;

        while (Organizer::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
