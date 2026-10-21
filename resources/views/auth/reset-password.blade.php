@extends('layouts.app')

@section('title', 'Set a new password — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <h1 class="font-display text-2xl font-black">Set a new password</h1>
        <p class="mt-1 text-sm text-slate-500">Choose a password of at least eight characters.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">New password</label>
                <input type="password" name="password" required autocomplete="new-password" class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Confirm new password</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="input-cyber text-sm">
            </div>

            <button class="btn-neon w-full rounded-xl px-4 py-3">Update password</button>
        </form>
    </div>
</section>
@endsection
