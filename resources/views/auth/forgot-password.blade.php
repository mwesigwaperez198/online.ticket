@extends('layouts.app')

@section('title', 'Recover your account — '.config('app.name', 'Novara Tickets'))

@section('content')
<section class="mx-auto flex min-h-[70vh] max-w-md items-center px-4 py-16">
    <div class="panel w-full p-8">
        <h1 class="font-display text-2xl font-black">Recover your account</h1>
        <p class="mt-1 text-sm text-slate-500">
            Enter the email address on your account and we will send you a link to set a new password.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input-cyber text-sm">
            </div>

            <button class="btn-neon w-full rounded-xl px-4 py-3">Send reset link</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Remembered it?
            <a href="{{ route('login') }}" class="text-neon hover:underline">Back to sign in</a>
        </p>

        <p class="mt-4 rounded-lg border border-edge/70 bg-void/50 px-3 py-2 text-xs text-slate-400">
            Lost the email as well? Message us on WhatsApp
            <a class="text-neon underline" href="{{ config('ticketing.brand.whatsapp_link') }}" target="_blank" rel="noopener">{{ config('ticketing.brand.whatsapp') }}</a>
            and we will verify ownership before restoring access.
        </p>
    </div>
</section>
@endsection
