@extends('layouts.app')

@section('title', 'Profile settings — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Profile settings</h1>
    <p class="mt-2 text-sm text-slate-500">Update your contact details and change your password. Password changes require your current password.</p>

    <form method="POST" action="{{ route('profile.update') }}" class="panel mt-8 space-y-5 p-6">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Full name</label>
            <input name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" class="input-cyber text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email" class="input-cyber text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Phone</label>
            <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" class="input-cyber text-sm">
        </div>
        <div class="divider"></div>
        <h2 class="font-display text-lg font-bold">Change password</h2>
        <div>
            <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Current password</label>
            <input type="password" name="current_password" autocomplete="current-password" class="input-cyber text-sm">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">New password</label>
                <input type="password" name="password" autocomplete="new-password" class="input-cyber text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Confirm new password</label>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="input-cyber text-sm">
            </div>
        </div>
        <button class="btn-neon w-full rounded-xl px-4 py-3">Save profile settings</button>
    </form>
</section>
@endsection
