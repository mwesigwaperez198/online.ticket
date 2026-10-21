@extends('layouts.app')

@section('title', 'Sign in — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <h1 class="font-display text-2xl font-black">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to manage tickets, listings and payouts.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Password</label>
                <input type="password" name="password" required autocomplete="current-password" class="input-cyber text-sm">
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-xs text-slate-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-edge bg-void">
                    Keep me signed in
                </label>
                <a href="{{ route('password.request') }}" class="text-xs text-neon hover:underline">Forgot password?</a>
            </div>

            <button class="btn-neon w-full rounded-xl px-4 py-3">Sign in</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            No account yet?
            <a href="{{ route('register') }}" class="text-neon hover:underline">Create one</a>
        </p>
    </div>
</section>
@endsection
