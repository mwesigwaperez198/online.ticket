<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Novara Tickets'))</title>
    <meta name="description" content="@yield('meta_description', 'Novara Tickets — Mobile Money ticketing and booking in Uganda. Buy event tickets, services and gate passes with MTN Mobile Money and Airtel Money.')">
    <meta name="keywords" content="tickets Uganda, Mobile Money tickets, MTN MoMo Uganda, Airtel Money, event tickets Kampala, Novara Tickets">
    <meta name="author" content="{{ config('ticketing.brand.company', 'Novara Tech Africa') }}">
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'Novara Tickets') }}">
    <meta property="og:title" content="@yield('title', config('app.name', 'Novara Tickets'))">
    <meta property="og:description" content="@yield('meta_description', 'Mobile Money ticketing and booking for Uganda. Everything by Novara.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:locale" content="en_UG">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' fill='%2322D3EE'>N</text></svg>">

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        void: '#0B0F19',
                        panel: '#111827',
                        edge: '#1F2937',
                        neon: '#22D3EE',
                        pulse: '#F472B6',
                        acid: '#A3E635',
                        amber: '#FBBF24',
                    },
                    boxShadow: {
                        glow: '0 0 40px -12px rgba(34,211,238,0.55)',
                        pulse: '0 0 40px -12px rgba(244,114,182,0.55)',
                    },
                    fontFamily: {
                        display: ['Space Grotesk', 'Inter', 'system-ui', 'sans-serif'],
                        body: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        :root { color-scheme: dark; }
        body {
            background:
                radial-gradient(1100px 600px at 12% -10%, rgba(34,211,238,.14), transparent 60%),
                radial-gradient(900px 520px at 92% 0%, rgba(244,114,182,.14), transparent 55%),
                #0B0F19;
            color: #E5E7EB;
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
        }
        .font-display { font-family: 'Space Grotesk', 'Inter', system-ui, sans-serif; }
        .panel {
            background: linear-gradient(180deg, rgba(17,24,39,.92), rgba(11,15,25,.92));
            border: 1px solid rgba(31,41,55,.9);
            border-radius: 1rem;
            box-shadow: 0 24px 60px -30px rgba(0,0,0,.9), inset 0 1px 0 rgba(255,255,255,.04);
        }
        .panel-hover { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
        .panel-hover:hover {
            transform: translateY(-4px);
            border-color: rgba(34,211,238,.45);
            box-shadow: 0 30px 70px -30px rgba(34,211,238,.35), inset 0 1px 0 rgba(255,255,255,.06);
        }
        .neon-text {
            background: linear-gradient(92deg, #22D3EE 0%, #F472B6 55%, #A3E635 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .btn-neon {
            background: linear-gradient(92deg, #22D3EE, #6366F1);
            color: #05070D; font-weight: 700;
            box-shadow: 0 10px 30px -12px rgba(34,211,238,.85);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }
        .btn-neon:hover { transform: translateY(-1px); filter: brightness(1.08); box-shadow: 0 16px 40px -14px rgba(34,211,238,1); }
        .btn-ghost { border: 1px solid rgba(148,163,184,.28); color: #E2E8F0; transition: border-color .2s ease, background .2s ease; }
        .btn-ghost:hover { border-color: rgba(34,211,238,.6); background: rgba(34,211,238,.07); }
        .pill { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; padding: .2rem .6rem; border-radius: 999px; border: 1px solid transparent; }
        .pill-live { color: #A3E635; border-color: rgba(163,230,53,.45); background: rgba(163,230,53,.08); }
        .pill-pending { color: #FBBF24; border-color: rgba(251,191,36,.45); background: rgba(251,191,36,.08); }
        .pill-closed { color: #F87171; border-color: rgba(248,113,113,.45); background: rgba(248,113,113,.08); }
        .pill-info { color: #22D3EE; border-color: rgba(34,211,238,.45); background: rgba(34,211,238,.08); }
        .pill-paid { color: #A3E635; border-color: rgba(163,230,53,.45); background: rgba(163,230,53,.08); }
        .grid-lines {
            background-image: linear-gradient(rgba(148,163,184,.055) 1px, transparent 1px),
                            linear-gradient(90deg, rgba(148,163,184,.055) 1px, transparent 1px);
            background-size: 46px 46px;
        }
        .divider-glow { height: 1px; background: linear-gradient(90deg, transparent, rgba(34,211,238,.45), rgba(244,114,182,.45), transparent); }
        .accordion-body { max-height: 0; overflow: hidden; transition: max-height .35s ease, opacity .3s ease; opacity: 0; }
        .accordion-body.open { max-height: 1200px; opacity: 1; }

        /* Decorative light rig across the top of every page. */
        .light-rig { position: fixed; top: 0; left: 0; right: 0; height: 130px; pointer-events: none; z-index: 30; overflow: hidden; }
        .light-rig span { position: absolute; display: block; border-radius: 999px; filter: blur(42px); opacity: .55; animation: rig-drift 14s ease-in-out infinite alternate; }
        .light-rig .rig-1 { width: 320px; height: 120px; top: -52px; left: 8%; background: rgba(34,211,238,.75); }
        .light-rig .rig-2 { width: 280px; height: 110px; top: -46px; left: 38%; background: rgba(99,102,241,.7); animation-delay: -4s; }
        .light-rig .rig-3 { width: 300px; height: 110px; top: -50px; right: 12%; background: rgba(244,114,182,.6); animation-delay: -8s; }
        .light-rig::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(11,15,25,.35), rgba(11,15,25,0) 70%);
        }
        @keyframes rig-drift {
            from { transform: translate3d(-12px, 0, 0) scale(1); opacity: .45; }
            to   { transform: translate3d(16px, 10px, 0) scale(1.18); opacity: .7; }
        }
        @media (prefers-reduced-motion: reduce) {
            .light-rig span { animation: none; }
        }

        /*
         * The Tailwind forms plugin injects base styles for every input after
         * this stylesheet is parsed, so the cyber input rules below are
         * declared with !important to keep dark surfaces and legible text.
         */
        input, select, textarea { color-scheme: dark; }
        .input-cyber {
            background-color: rgba(11,15,25,.85) !important;
            background-image: none !important;
            border: 1px solid rgba(148,163,184,.28) !important;
            border-radius: .6rem;
            padding: .6rem .8rem;
            width: 100%;
            color: #E2E8F0 !important;
            -webkit-text-fill-color: #E2E8F0 !important;
            box-shadow: none !important;
        }
        .input-cyber::placeholder { color: #94A3B8 !important; -webkit-text-fill-color: #94A3B8 !important; opacity: 1; }
        .input-cyber:focus {
            border-color: rgba(34,211,238,.75) !important;
            box-shadow: 0 0 0 3px rgba(34,211,238,.14) !important;
        }
        .input-cyber:disabled, .input-cyber[readonly] { opacity: .7; cursor: not-allowed; }
        select.input-cyber option { background: #0B0F19; color: #E2E8F0; }
        input[type="checkbox"].input-cyber, input[type="radio"].input-cyber {
            width: 1rem; height: 1rem; padding: 0; accent-color: #22D3EE;
        }
        table { border-collapse: collapse; }
        .data-table th { color: #94A3B8; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; text-align: left; padding: .6rem .75rem; }
        .data-table td { padding: .7rem .75rem; border-top: 1px solid rgba(31,41,55,.9); font-size: .9rem; }
        .data-table tr:hover td { background: rgba(34,211,238,.04); }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: #0B0F19; }
        ::-webkit-scrollbar-thumb { background: #1F2937; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
        .leaflet-container { background: #0B0F19; font: inherit; }
    </style>
</head>
<body class="antialiased">
<div class="light-rig" aria-hidden="true">
    <span class="rig-1"></span><span class="rig-2"></span><span class="rig-3"></span>
</div>
<div class="min-h-screen flex flex-col">

    <header class="sticky top-0 z-40 border-b border-edge/80 bg-void/85 backdrop-blur-xl">
        <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-neon to-pulse font-display text-void font-black">N</span>
                    <span class="font-display text-lg font-bold tracking-tight">NOVARA<span class="neon-text">TICKETS</span></span>
                </a>

                <div class="hidden items-center gap-6 md:flex">
                    <a href="{{ route('home') }}" class="text-sm text-slate-300 hover:text-neon transition">Browse</a>
                    @auth
                        <a href="{{ route('buyer.tickets') }}" class="text-sm text-slate-300 hover:text-neon transition">My Tickets</a>
                        @if(auth()->user()->isOrganizer())
                            <a href="{{ route('organizer.dashboard') }}" class="text-sm text-slate-300 hover:text-neon transition">Organizer</a>
                        @endif
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-300 hover:text-pulse transition">Admin CMS</a>
                        @endif
                    @endauth
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <span class="hidden sm:inline text-xs text-slate-400">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn-ghost rounded-lg px-3 py-2 text-sm">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-ghost rounded-lg px-3 py-2 text-sm">Sign in</a>
                        <a href="{{ route('register') }}" class="btn-neon rounded-lg px-4 py-2 text-sm">Get started</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    @if(session('status'))
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-acid/40 bg-acid/10 px-4 py-3 text-sm text-acid">{{ session('status') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ session('error') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-edge/70 bg-void/60">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-sm">
                    <p class="font-display text-lg font-bold">NOVARA<span class="neon-text">TICKETS</span></p>
                    <p class="mt-2 text-sm text-slate-500">Mobile Money ticketing for events and services in Uganda. {{ config('ticketing.brand.tagline', 'Everything by Novara.') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-400">
                        <span class="rounded-full border border-edge px-3 py-1">MTN Mobile Money</span>
                        <span class="rounded-full border border-edge px-3 py-1">Airtel Money</span>
                    </div>
                </div>

                <div class="grid gap-8 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Platform</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-500">
                            <li><a class="transition hover:text-neon" href="{{ route('home') }}">Browse events</a></li>
                            <li><a class="transition hover:text-neon" href="{{ route('register') }}">Create an account</a></li>
                            <li><a class="transition hover:text-neon" href="{{ route('login') }}">Sign in</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Novara Tech Africa</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-500">
                            <li><a class="transition hover:text-neon" href="{{ config('ticketing.brand.website') }}" target="_blank" rel="noopener">Website</a></li>
                            <li><a class="transition hover:text-neon" href="{{ config('ticketing.brand.instagram') }}" target="_blank" rel="noopener">Instagram</a></li>
                            <li><a class="transition hover:text-neon" href="{{ config('ticketing.brand.whatsapp_link') }}" target="_blank" rel="noopener">WhatsApp {{ config('ticketing.brand.whatsapp') }}</a></li>
                            <li><a class="transition hover:text-neon" href="mailto:{{ config('ticketing.brand.email') }}">{{ config('ticketing.brand.email') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Support</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-500">
                            <li>{{ config('ticketing.brand.support_hours') }}</li>
                            <li>Paid via Mobile Money</li>
                            <li>Secured by Paystack</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="divider-glow my-8"></div>
            <p class="text-xs text-slate-600">© {{ date('Y') }} {{ config('ticketing.brand.company', 'Novara Tech Africa') }}. Every ticket is issued with a single-use cryptographic QR code.</p>
        </div>
    </footer>
</div>

@include('partials.help')

@stack('scripts')
</body>
</html>
