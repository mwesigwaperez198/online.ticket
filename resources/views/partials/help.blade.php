@php $helpFee = \App\Support\Money::percent((int) setting('platform_commission_bps', 2000)); @endphp

<div class="fixed bottom-5 right-5 z-50 print:hidden">

    <div id="help-panel" class="hidden panel w-[min(22rem,calc(100vw-2rem))] overflow-hidden shadow-glow mb-3">
        <div class="flex items-center justify-between px-4 py-3 border-b border-edge/60">
            <div>
                <p class="font-display text-sm font-bold text-slate-100">Help Centre</p>
                <p class="text-xs text-slate-500">Quick answers, no queue.</p>
            </div>
            <button data-help-close class="text-xs text-slate-500 hover:text-neon transition px-2 py-1 rounded border border-edge hover:border-neon/40">✕</button>
        </div>

        <div class="flex gap-1 px-3 py-2 border-b border-edge/60 overflow-x-auto">
            @foreach(['selling' => 'Selling', 'buying' => 'Buying', 'account' => 'Account', 'about' => 'About'] as $tab => $label)
                <button data-help-tab="{{ $tab }}"
                        class="help-tab shrink-0 rounded-md px-2.5 py-1.5 text-xs font-medium transition
                               {{ $tab === 'selling' ? 'bg-neon/10 text-neon' : 'text-slate-500 hover:text-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div data-help-panel="selling" class="max-h-[55vh] overflow-y-auto px-4 py-4 text-sm text-slate-400 space-y-3">
            <p class="font-semibold text-slate-200">Sell your event in 5 steps</p>
            <ol class="space-y-2">
                <li><span class="text-neon font-bold">1.</span> Create an account and tick <em>I want to sell tickets</em>.</li>
                <li><span class="text-neon font-bold">2.</span> Add your MoMo payout number in <em>Organizer → Payout settings</em>.</li>
                <li><span class="text-neon font-bold">3.</span> Create your listing with venue, date and ticket tiers.</li>
                <li><span class="text-neon font-bold">4.</span> Admin approves your account. Default platform fee is <strong class="text-acid">{{ $helpFee }}</strong>.</li>
                <li><span class="text-neon font-bold">5.</span> Share your link. Every sale splits automatically to your wallet.</li>
            </ol>
        </div>

        <div data-help-panel="buying" class="hidden max-h-[55vh] overflow-y-auto px-4 py-4 text-sm text-slate-400 space-y-3">
            <p class="font-semibold text-slate-200">Buy a ticket in 3 steps</p>
            <ol class="space-y-2">
                <li><span class="text-neon font-bold">1.</span> Open the event and pick your ticket tier.</li>
                <li><span class="text-neon font-bold">2.</span> Enter your name, email and Uganda MoMo number.</li>
                <li><span class="text-neon font-bold">3.</span> Approve the PIN prompt on your phone — QR ticket issued instantly.</li>
            </ol>
        </div>

        <div data-help-panel="account" class="hidden max-h-[55vh] overflow-y-auto px-4 py-4 text-sm text-slate-400 space-y-3">
            <p class="font-semibold text-slate-200">Account help</p>
            <p>Forgot your password? Use <a href="{{ route('password.request') }}" class="text-neon underline">Forgot password</a> on the sign-in page.</p>
            <p>Lost access to your email? WhatsApp us at <a href="{{ config('ticketing.brand.whatsapp_link') }}" class="text-neon underline" target="_blank">{{ config('ticketing.brand.whatsapp') }}</a> and we'll verify ownership.</p>
        </div>

        <div data-help-panel="about" class="hidden max-h-[55vh] overflow-y-auto px-4 py-4 text-sm text-slate-400 space-y-3">
            <p class="font-semibold text-slate-200">About Novara</p>
            <p>{{ config('ticketing.brand.company') }} builds Uganda's Mobile Money ticketing platform. Buy tickets, book services, run the gate — all paid by MoMo.</p>
            <div class="space-y-2 pt-1">
                <a href="{{ config('ticketing.brand.whatsapp_link') }}" target="_blank" rel="noopener"
                   class="flex items-center gap-2 rounded-lg border border-edge px-3 py-2 text-xs hover:border-neon/40 hover:text-neon transition">
                    💬 WhatsApp {{ config('ticketing.brand.whatsapp') }}
                </a>
                <a href="{{ config('ticketing.brand.instagram') }}" target="_blank" rel="noopener"
                   class="flex items-center gap-2 rounded-lg border border-edge px-3 py-2 text-xs hover:border-neon/40 hover:text-neon transition">
                    📸 Instagram
                </a>
            </div>
        </div>
    </div>

    <button id="help-toggle" aria-expanded="false"
            class="ml-auto flex h-13 w-13 items-center justify-center rounded-full bg-gradient-to-br from-neon to-pulse text-void shadow-glow hover:scale-105 transition-transform focus:outline-none"
            style="height:3.25rem;width:3.25rem">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M9.6 9a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .8-1 1.5v.4"/>
            <path d="M12 17h.01"/>
        </svg>
        <span class="sr-only">Help</span>
    </button>
</div>

<script>
(function () {
    const toggle  = document.getElementById('help-toggle');
    const panel   = document.getElementById('help-panel');
    const tabs    = panel.querySelectorAll('[data-help-tab]');
    const sections = panel.querySelectorAll('[data-help-panel]');

    function activate(tab) {
        tabs.forEach(b => {
            const on = b.dataset.helpTab === tab;
            b.classList.toggle('bg-neon/10', on);
            b.classList.toggle('text-neon', on);
            b.classList.toggle('text-slate-500', !on);
        });
        sections.forEach(s => s.classList.toggle('hidden', s.dataset.helpPanel !== tab));
    }

    toggle.addEventListener('click', () => {
        const open = !panel.classList.contains('hidden');
        panel.classList.toggle('hidden', open);
        toggle.setAttribute('aria-expanded', String(!open));
    });

    panel.querySelector('[data-help-close]').addEventListener('click', () => {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    });

    tabs.forEach(b => b.addEventListener('click', () => activate(b.dataset.helpTab)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') panel.classList.add('hidden'); });
})();
</script>
