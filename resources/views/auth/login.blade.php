@extends('layouts.app')

@section('title', 'Sign in — Novara Tickets')

@section('content')
<section class="min-h-[80vh] flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-sm animate-fade-up">

        <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-neon to-pulse mb-4">
                <span class="font-display font-black text-void text-lg">N</span>
            </div>
            <h1 class="font-display text-2xl font-black text-slate-100">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to your Novara account</p>
        </div>

        <div class="panel p-7">
            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Email address</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="email"
                           class="input-cyber" placeholder="you@example.com">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold uppercase tracking-widest text-slate-500">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-neon hover:text-acid transition">Forgot?</a>
                    </div>
                    <input type="password" name="password"
                           required autocomplete="current-password"
                           class="input-cyber" placeholder="••••••••">
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-edge bg-void accent-neon">
                    <span class="text-sm text-slate-400">Keep me signed in</span>
                </label>

                <button class="btn-primary w-full rounded-xl py-3 text-sm mt-2">
                    Sign in
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500">
            No account?
            <a href="{{ route('register') }}" class="text-neon hover:text-acid transition font-medium">Create one free</a>
        </p>
    </div>
</section>
@endsection
