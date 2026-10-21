@extends('layouts.app')

@section('title', 'Create account — Pulse Tickets')

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <h1 class="font-display text-2xl font-black">Create your account</h1>
        <p class="mt-1 text-sm text-slate-500">Buy tickets, or start selling as an organizer.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Full name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Phone</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="024 000 0000" required class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Password</label>
                <input type="password" name="password" required class="input-cyber text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Confirm password</label>
                <input type="password" name="password_confirmation" required class="input-cyber text-sm">
            </div>

            <div class="rounded-xl border border-edge/80 bg-void/50 p-4">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="business_name_present" checked class="mt-1 rounded border-edge bg-void" onchange="document.getElementById('business').disabled = !this.checked">
                    <span>
                        <span class="block font-medium text-slate-200">I want to sell tickets</span>
                        <span class="block text-xs text-slate-500">Your organizer account is created and reviewed by the platform before you can publish.</span>
                    </span>
                </label>
                <div class="mt-3" id="business-wrap">
                    <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Business / promoter name</label>
                    <input type="text" name="business_name" id="business" value="{{ old('business_name') }}" class="input-cyber text-sm">
                </div>
            </div>

            <button class="btn-neon w-full rounded-xl px-4 py-3">Create account</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Already registered?
            <a href="{{ route('login') }}" class="text-neon hover:underline">Sign in</a>
        </p>
    </div>
</section>
@endsection
