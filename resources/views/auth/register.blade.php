@extends('layouts.app')

@section('title', 'Create account — Novara Tickets')

@section('content')
<section class="min-h-[80vh] flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-sm animate-fade-up">

        <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-neon to-pulse mb-4">
                <span class="font-display font-black text-void text-lg">N</span>
            </div>
            <h1 class="font-display text-2xl font-black text-slate-100">Join Novara</h1>
            <p class="mt-1 text-sm text-slate-500">Buy tickets or start selling events</p>
        </div>

        <div class="panel p-7">
            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           required autofocus class="input-cyber" placeholder="Your name">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           required class="input-cyber" placeholder="you@example.com">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Phone number</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                           required class="input-cyber" placeholder="0772 123 456">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Password</label>
                        <input type="password" name="password" required class="input-cyber" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Confirm</label>
                        <input type="password" name="password_confirmation" required class="input-cyber" placeholder="••••••••">
                    </div>
                </div>

                <div class="rounded-xl border border-edge bg-void/60 p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="business_name_present" checked
                               class="mt-0.5 h-4 w-4 rounded border-edge bg-void accent-neon"
                               onchange="document.getElementById('business-wrap').classList.toggle('hidden', !this.checked)">
                        <div>
                            <span class="block text-sm font-medium text-slate-200">I want to sell tickets</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Your organizer account is reviewed before your first listing goes live.</span>
                        </div>
                    </label>
                    <div id="business-wrap" class="mt-3">
                        <label class="block text-xs font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Business / promoter name</label>
                        <input type="text" name="business_name" id="business"
                               value="{{ old('business_name') }}"
                               class="input-cyber" placeholder="e.g. Kampala Nights">
                    </div>
                </div>

                <button class="btn-primary w-full rounded-xl py-3 text-sm mt-1">
                    Create account
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="text-neon hover:text-acid transition font-medium">Sign in</a>
        </p>
    </div>
</section>
@endsection
