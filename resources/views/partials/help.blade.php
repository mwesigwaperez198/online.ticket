@php
    $helpCommission = (int) setting('platform_commission_bps', 2000);
    $helpCommissionLabel = \App\Support\Money::percent($helpCommission);
@endphp
<div class="fixed bottom-5 right-5 z-50 print:hidden">
    <div id="novara-help-panel" class="panel hidden w-[min(24rem,calc(100vw-2.5rem))] overflow-hidden shadow-glow">
        <div class="flex items-start justify-between gap-3 border-b border-edge/70 px-4 py-3">
            <div>
                <p class="font-display text-sm font-bold">Novara Help Centre</p>
                <p class="text-xs text-slate-500">Everything by Novara — quick answers, no call centre queue.</p>
            </div>
            <button type="button" data-help-close class="rounded-md border border-edge px-2 py-1 text-xs text-slate-400 transition hover:border-neon hover:text-neon">Close</button>
        </div>

        <div class="flex flex-wrap gap-1 border-b border-edge/70 px-3 py-2 text-xs">
            <button type="button" data-help-tab="selling" class="help-tab rounded-md bg-neon/15 px-2.5 py-1.5 font-semibold text-neon">Selling</button>
            <button type="button" data-help-tab="buying" class="help-tab rounded-md px-2.5 py-1.5 text-slate-400 transition hover:text-neon">Buying</button>
            <button type="button" data-help-tab="account" class="help-tab rounded-md px-2.5 py-1.5 text-slate-400 transition hover:text-neon">Account</button>
            <button type="button" data-help-tab="novara" class="help-tab rounded-md px-2.5 py-1.5 text-slate-400 transition hover:text-neon">About Novara</button>
        </div>

        <div class="max-h-[60vh] space-y-4 overflow-y-auto px-4 py-4 text-sm text-slate-300" data-help-panel="selling">
            <p class="font-semibold text-slate-100">Sell your event or service in five steps</p>
            <ol class="space-y-2">
                <li><span class="font-semibold text-neon">1.</span> Create an account and choose <em>event organiser</em> so your workspace unlocks.</li>
                <li><span class="font-semibold text-neon">2.</span> Add your Mobile Money number in <em>Organizer → Payout settings</em>. This is the number that receives your share.</li>
                <li><span class="font-semibold text-neon">3.</span> Save your listing — event or service — with the venue, date and ticket tiers.</li>
                <li><span class="font-semibold text-neon">4.</span> The admin approves your account and confirms your commission rate. The default platform fee is <strong class="text-acid">{{ $helpCommissionLabel }}</strong>, and the admin can approve a different rate per organiser.</li>
                <li><span class="font-semibold text-neon">5.</span> Share the listing link. Every sale is paid instantly by Mobile Money and your share lands in your wallet balance.</li>
            </ol>
            <p class="rounded-lg border border-edge/70 bg-void/50 px-3 py-2 text-xs text-slate-400">
                Payouts are settled to the Mobile Money account you submit. Submitting a new number sends it back for re-approval.
            </p>
        </div>

        <div class="hidden max-h-[60vh] space-y-4 overflow-y-auto px-4 py-4 text-sm text-slate-300" data-help-panel="buying">
            <p class="font-semibold text-slate-100">Buy a ticket in three steps</p>
            <ol class="space-y-2">
                <li><span class="font-semibold text-neon">1.</span> Open the event page and pick a ticket tier — every tier shows what is left and the price in shillings.</li>
                <li><span class="font-semibold text-neon">2.</span> Enter your name, email and Uganda Mobile Money number, then press <em>Request MoMo PIN Prompt</em>.</li>
                <li><span class="font-semibold text-neon">3.</span> Approve the prompt on your phone. The ticket is issued instantly with a QR code you show at the gate.</li>
            </ol>
            <p class="text-xs text-slate-400">You can review every order and ticket from <em>My Tickets</em> once you sign in.</p>
        </div>

        <div class="hidden max-h-[60vh] space-y-4 overflow-y-auto px-4 py-4 text-sm text-slate-300" data-help-panel="account">
            <div>
                <p class="font-semibold text-slate-100">Create an account</p>
                <ol class="mt-2 space-y-2">
                    <li><span class="font-semibold text-neon">1.</span> Press <em>Get started</em> and fill in your name, email, phone and a password of at least eight characters.</li>
                    <li><span class="font-semibold text-neon">2.</span> Add a business name only if you are selling tickets — that creates your organiser workspace.</li>
                </ol>
            </div>
            <div>
                <p class="font-semibold text-slate-100">Recover an account</p>
                <ol class="mt-2 space-y-2">
                    <li><span class="font-semibold text-neon">1.</span> Forgot your password? Press <em>Forgot password</em> on the sign-in page.</li>
                    <li><span class="font-semibold text-neon">2.</span> Enter the email on the account — a reset link is sent straight to it.</li>
                    <li><span class="font-semibold text-neon">3.</span> Open the link, choose a new password, then sign in again.</li>
                    <li><span class="font-semibold text-neon">4.</span> If the email itself is gone, contact us on WhatsApp {{ config('ticketing.brand.whatsapp') }} and we will verify ownership before restoring access.</li>
                </ol>
                <p class="mt-3"><a class="font-semibold text-neon underline" href="{{ route('password.request') }}">Recover my account</a></p>
            </div>
        </div>

        <div class="hidden max-h-[60vh] space-y-4 overflow-y-auto px-4 py-4 text-sm text-slate-300" data-help-panel="novara">
            <p class="font-semibold text-slate-100">What to know about Novara</p>
            <p>{{ config('ticketing.brand.company') }} builds {{ config('ticketing.brand.name') }}, the Mobile Money ticketing marketplace for Uganda. Buy tickets, book services and run the gate — all paid by MTN Mobile Money or Airtel Money.</p>
            <ul class="space-y-1.5 text-xs text-slate-400">
                <li>Tickets are issued instantly with a single-use QR code.</li>
                <li>Every organiser is approved by the admin team before their first payout.</li>
                <li>The platform fee is {{ $helpCommissionLabel }} by default and is confirmed per organiser by the admin.</li>
                <li>Support: {{ config('ticketing.brand.support_hours') }}</li>
            </ul>
            <div class="space-y-2 pt-1">
                <a class="block rounded-lg border border-neon/40 bg-neon/10 px-3 py-2 text-center font-semibold text-neon transition hover:bg-neon/20" href="{{ config('ticketing.brand.website') }}" target="_blank" rel="noopener">
                    Visit {{ config('ticketing.brand.website') }}
                </a>
                <a class="block rounded-lg border border-edge px-3 py-2 text-center text-slate-300 transition hover:border-neon hover:text-neon" href="{{ config('ticketing.brand.instagram') }}" target="_blank" rel="noopener">
                    Instagram — @novara_tech_uganda
                </a>
                <a class="block rounded-lg border border-edge px-3 py-2 text-center text-slate-300 transition hover:border-neon hover:text-neon" href="{{ config('ticketing.brand.whatsapp_link') }}" target="_blank" rel="noopener">
                    WhatsApp — {{ config('ticketing.brand.whatsapp') }}
                </a>
            </div>
        </div>
    </div>

    <button type="button" id="novara-help-toggle" aria-expanded="false" aria-controls="novara-help-panel"
            class="group grid h-14 w-14 place-items-center rounded-full bg-gradient-to-br from-neon to-pulse text-void shadow-glow transition hover:scale-105 focus:outline-none focus:ring-2 focus:ring-neon focus:ring-offset-2 focus:ring-offset-void">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M9.6 9a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .8-1 1.5v.4"></path>
            <path d="M12 17h.01"></path>
        </svg>
        <span class="sr-only">Open the Novara help centre</span>
    </button>
</div>

<script>
    (function () {
        var toggle = document.getElementById('novara-help-toggle');
        var panel = document.getElementById('novara-help-panel');
        if (!toggle || !panel) { return; }

        var tabs = panel.querySelectorAll('[data-help-tab]');
        var sections = panel.querySelectorAll('[data-help-panel]');

        function activate(tab) {
            tabs.forEach(function (button) {
                var active = button.getAttribute('data-help-tab') === tab;
                button.classList.toggle('bg-neon/15', active);
                button.classList.toggle('font-semibold', active);
                button.classList.toggle('text-neon', active);
                button.classList.toggle('text-slate-400', !active);
            });
            sections.forEach(function (section) {
                section.classList.toggle('hidden', section.getAttribute('data-help-panel') !== tab);
            });
        }

        function open() {
            panel.classList.remove('hidden');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function close() {
            panel.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            if (panel.classList.contains('hidden')) { open(); } else { close(); }
        });

        panel.querySelector('[data-help-close]').addEventListener('click', close);

        tabs.forEach(function (button) {
            button.addEventListener('click', function () {
                activate(button.getAttribute('data-help-tab'));
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); }
        });
    })();
</script>
