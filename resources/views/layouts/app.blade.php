<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Novara Tickets'))</title>
    <meta name="description" content="@yield('meta_description', 'Novara Tickets — buy event tickets across Uganda with MTN Mobile Money. Instant QR delivery.')">
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', config('app.name', 'Novara Tickets'))">
    <meta property="og:description" content="@yield('meta_description', 'Mobile Money ticketing for Uganda.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' fill='%2322D3EE'>N</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        void: '#080C14',
                        panel: '#0E1420',
                        edge: '#1A2235',
                        neon: '#00E5FF',
                        pulse: '#FF4D8D',
                        acid: '#AAFF00',
                        amber: '#FFB800',
                        muted: '#64748B',
                    },
                    fontFamily: {
                        display: ['Space Grotesk', 'system-ui', 'sans-serif'],
                        body: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        glow: '0 0 50px -10px rgba(0,229,255,0.4)',
                        'glow-pulse': '0 0 50px -10px rgba(255,77,141,0.4)',
                        'glow-acid': '0 0 50px -10px rgba(170,255,0,0.3)',
                    },
                }
            }
        }
    </script>
    <style>
        :root { color-scheme: dark; }

        body {
            background-color: #080C14;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -5%, rgba(0,229,255,.1) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 85% 5%, rgba(255,77,141,.1) 0%, transparent 55%);
            color: #CBD5E1;
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
        }

        .font-display { font-family: 'Space Grotesk', system-ui, sans-serif; }

        .panel {
            background: rgba(14,20,32,.85);
            border: 1px solid rgba(26,34,53,.9);
            border-radius: 1rem;
            backdrop-filter: blur(12px);
        }

        .panel-lift {
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .panel-lift:hover {
            transform: translateY(-3px);
            border-color: rgba(0,229,255,.3);
            box-shadow: 0 20px 60px -20px rgba(0,229,255,.2);
        }

        .neon-text {
            background: linear-gradient(100deg, #00E5FF 0%, #FF4D8D 60%, #AAFF00 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00E5FF, #0066FF);
            color: #080C14;
            font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: .02em;
            transition: filter .15s ease, transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 8px 24px -8px rgba(0,229,255,.6);
        }
        .btn-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 12px 32px -8px rgba(0,229,255,.8);
        }
        .btn-primary:active { transform: translateY(0); }

        .btn-outline {
            border: 1px solid rgba(100,116,139,.4);
            color: #CBD5E1;
            font-family: 'Space Grotesk', sans-serif;
            transition: border-color .15s ease, background .15s ease, color .15s ease;
        }
        .btn-outline:hover {
            border-color: rgba(0,229,255,.5);
            background: rgba(0,229,255,.06);
            color: #00E5FF;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: .2rem .65rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }
        .pill-live    { color: #AAFF00; border-color: rgba(170,255,0,.35);  background: rgba(170,255,0,.08); }
        .pill-pending { color: #FFB800; border-color: rgba(255,184,0,.35);  background: rgba(255,184,0,.08); }
        .pill-closed  { color: #FF6B6B; border-color: rgba(255,107,107,.35); background: rgba(255,107,107,.08); }
        .pill-info    { color: #00E5FF; border-color: rgba(0,229,255,.35);  background: rgba(0,229,255,.08); }
        .pill-paid    { color: #AAFF00; border-color: rgba(170,255,0,.35);  background: rgba(170,255,0,.08); }
        .pill-pulse   { color: #FF4D8D; border-color: rgba(255,77,141,.35); background: rgba(255,77,141,.08); }

        .input-field {
            background: rgba(8,12,20,.9) !important;
            border: 1px solid rgba(26,34,53,1) !important;
            border-radius: .6rem;
            padding: .65rem .9rem;
            width: 100%;
            color: #E2E8F0 !important;
            -webkit-text-fill-color: #E2E8F0 !important;
            font-size: .9rem;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .input-field::placeholder { color: #475569 !important; -webkit-text-fill-color: #475569 !important; }
        .input-field:focus {
            outline: none;
            border-color: rgba(0,229,255,.6) !important;
            box-shadow: 0 0 0 3px rgba(0,229,255,.1) !important;
        }
        select.input-field option { background: #080C14; color: #E2E8F0; }

        .data-table th {
            color: #475569;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-weight: 600;
            padding: .75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(26,34,53,1);
        }
        .data-table td {
            padding: .8rem 1rem;
            border-bottom: 1px solid rgba(26,34,53,.6);
            font-size: .875rem;
        }
        .data-table tbody tr:hover td { background: rgba(0,229,255,.025); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(0,229,255,.3), rgba(255,77,141,.3), transparent); }

        .noise-bg {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
        }

        .grid-dot {
            background-image: radial-gradient(rgba(100,116,139,.15) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #080C14; }
        ::-webkit-scrollbar-thumb { background: #1A2235; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #243048; }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fade-up .5s ease both; }

        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(0,229,255,.4); }
            70%  { box-shadow: 0 0 0 10px rgba(0,229,255,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,229,255,0); }
        }
        .pulse-ring { animation: pulse-ring 2s ease infinite; }

        .accordion-body { max-height: 0; overflow: hidden; transition: max-height .4s ease, opacity .3s ease; opacity: 0; }
        .accordion-body.open { max-height: 1400px; opacity: 1; }

        input, select, textarea { color-scheme: dark; }
        .input-cyber {
            background-color: rgba(8,12,20,.9) !important;
            border: 1px solid rgba(26,34,53,1) !important;
            border-radius: .6rem;
            padding: .65rem .9rem;
            width: 100%;
            color: #E2E8F0 !important;
            -webkit-text-fill-color: #E2E8F0 !important;
        }
        .input-cyber::placeholder { color: #475569 !important; -webkit-text-fill-color: #475569 !important; opacity: 1; }
        .input-cyber:focus {
            border-color: rgba(0,229,255,.6) !important;
            box-shadow: 0 0 0 3px rgba(0,229,255,.1) !important;
        }
        select.input-cyber option { background: #080C14; color: #E2E8F0; }
    </style>
</head>
<body class="antialiased">

<div class="min-h-screen flex flex-col pb-20 md:pb-0">

    <header class="sticky top-0 z-40 border-b border-edge/60 bg-void/80 backdrop-blur-xl">
        <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-4">

                <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-neon to-pulse flex items-center justify-center">
                        <span class="font-display font-black text-void text-sm">N</span>
                    </div>
                    <span class="font-display font-bold text-base tracking-tight text-slate-100">
                        Novara<span class="text-neon">Tickets</span>
                    </span>
                </a>

                <div class="hidden md:flex items-center gap-1">
                    <a href="{{ route('home') }}" class="px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-100 hover:bg-edge/50 transition">Browse</a>
                    @auth
                        <a href="{{ route('buyer.tickets') }}" class="px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-100 hover:bg-edge/50 transition">My Tickets</a>
                        @if(auth()->user()->isOrganizer())
                            <a href="{{ route('organizer.dashboard') }}" class="px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-100 hover:bg-edge/50 transition">Dashboard</a>
                        @elseif(!auth()->user()->isAdmin())
                            <a href="{{ route('seller.onboarding') }}" class="px-3 py-2 rounded-lg text-sm text-neon hover:text-acid transition font-medium">Sell tickets</a>
                        @endif
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-lg text-sm text-pulse hover:text-slate-100 hover:bg-edge/50 transition">Admin</a>
                        @endif
                    @endauth
                </div>

                <div class="flex items-center gap-2">
                    @auth
                        <span class="hidden sm:block text-xs text-slate-500 max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn-outline rounded-lg px-3 py-1.5 text-sm">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-outline rounded-lg px-3 py-1.5 text-sm">Sign in</a>
                        <a href="{{ route('register') }}" class="btn-primary rounded-lg px-4 py-1.5 text-sm">Get started</a>
                    @endauth
                </div>

            </div>
        </nav>
    </header>

    <nav aria-label="Mobile navigation" class="fixed inset-x-0 bottom-0 z-50 border-t border-edge/80 bg-void/95 px-2 pt-2 backdrop-blur-xl md:hidden" style="padding-bottom: max(.5rem, env(safe-area-inset-bottom))">
        <div class="mx-auto flex max-w-lg items-stretch justify-around">
            <a href="{{ route('home') }}" @class(['flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] transition', 'text-neon' => request()->routeIs('home', 'events.*'), 'text-slate-400' => ! request()->routeIs('home', 'events.*')])>
                <span aria-hidden="true" class="text-lg leading-none">⌕</span><span>Browse</span>
            </a>
            @auth
                <a href="{{ route('buyer.tickets') }}" @class(['flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] transition', 'text-neon' => request()->routeIs('buyer.tickets', 'buyer.orders*'), 'text-slate-400' => ! request()->routeIs('buyer.tickets', 'buyer.orders*')])>
                    <span aria-hidden="true" class="text-lg leading-none">▣</span><span>My Tickets</span>
                </a>
                @if(auth()->user()->isOrganizer())
                    <a href="{{ route('organizer.dashboard') }}" @class(['flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] transition', 'text-neon' => request()->routeIs('organizer.*'), 'text-slate-400' => ! request()->routeIs('organizer.*')])>
                        <span aria-hidden="true" class="text-lg leading-none">◉</span><span>Dashboard</span>
                    </a>
                @elseif(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] text-slate-400 transition">
                        <span aria-hidden="true" class="text-lg leading-none">◉</span><span>Admin</span>
                    </a>
                @else
                    <a href="{{ route('seller.onboarding') }}" class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] text-slate-400 transition">
                        <span aria-hidden="true" class="text-lg leading-none">＋</span><span>Sell tickets</span>
                    </a>
                @endif
            @else
                <a href="{{ route('login') }}" class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-lg px-2 py-1.5 text-[.68rem] text-slate-400 transition">
                    <span aria-hidden="true" class="text-lg leading-none">⇥</span><span>Sign in</span>
                </a>
            @endauth
        </div>
    </nav>

    @if(session('status'))
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 rounded-xl border border-acid/30 bg-acid/8 px-4 py-3 text-sm text-acid">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('status') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 rounded-xl border border-red-500/30 bg-red-500/8 px-4 py-3 text-sm text-red-400">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-red-500/30 bg-red-500/8 px-4 py-3 text-sm text-red-400">
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="flex items-center gap-2">
                            <span class="h-1 w-1 rounded-full bg-red-400 shrink-0"></span>
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <main class="flex-1">
        @if(!request()->routeIs('home'))
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <button onclick="history.back()" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-200 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>
        </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-edge/60">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-10 lg:flex-row lg:justify-between">
                <div class="max-w-xs">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-md bg-gradient-to-br from-neon to-pulse flex items-center justify-center">
                            <span class="font-display font-black text-void text-xs">N</span>
                        </div>
                        <span class="font-display font-bold text-slate-200">Novara<span class="text-neon">Tickets</span></span>
                    </div>
                    <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                        Uganda's Mobile Money ticketing platform. Buy event tickets, book services, run the gate — all paid with MoMo.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach(config('ticketing.momo.providers') as $label)
                            <span class="pill pill-info">{{ $label }}</span>
                        @endforeach
                        @foreach(config('ticketing.momo.unavailable_providers', []) as $label)
                            <span class="pill" style="color:#475569;border-color:rgba(71,85,105,.3)">{{ $label }} soon</span>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">Platform</p>
                        <ul class="space-y-2 text-sm text-slate-500">
                            <li><a href="{{ route('home') }}" class="hover:text-neon transition">Browse events</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-neon transition">Create account</a></li>
                            <li><a href="{{ route('login') }}" class="hover:text-neon transition">Sign in</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">Novara Tech</p>
                        <ul class="space-y-2 text-sm text-slate-500">
                            <li><a href="{{ config('ticketing.brand.website') }}" target="_blank" rel="noopener" class="hover:text-neon transition">Website</a></li>
                            <li><a href="{{ config('ticketing.brand.instagram') }}" target="_blank" rel="noopener" class="hover:text-neon transition">Instagram</a></li>
                            <li><a href="{{ config('ticketing.brand.whatsapp_link') }}" target="_blank" rel="noopener" class="hover:text-neon transition">WhatsApp</a></li>
                            <li><a href="mailto:{{ config('ticketing.brand.email') }}" class="hover:text-neon transition">Email us</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">Support</p>
                        <ul class="space-y-2 text-sm text-slate-500">
                            <li>{{ config('ticketing.brand.support_hours') }}</li>
                            <li>Instant QR delivery</li>
                            <li>MTN MoMo secured</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="divider my-8"></div>
            <p class="text-xs text-slate-600">© {{ date('Y') }} {{ config('ticketing.brand.company', 'Novara Tech Africa') }}. Every ticket carries a single-use cryptographic QR code.</p>
        </div>
    </footer>
</div>

@include('partials.help')
@stack('scripts')
</body>
</html>
