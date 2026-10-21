@extends('layouts.app')

@section('title', 'Ticket scanner — Novara Tickets')

@section('content')
<section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">Gate scanner</h1>
    <p class="mt-1 text-sm text-slate-500">Validate a ticket hash or code against the server. Every attempt is logged.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_1fr]">
        <div class="panel p-6">
            <div>
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Event</label>
                <select id="listing" class="input-cyber text-sm">
                    <option value="">All my events</option>
                    @foreach($listings as $listing)
                        <option value="{{ $listing->id }}">{{ $listing->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-4">
                <label class="mb-1 block text-xs uppercase tracking-wider text-slate-500">Ticket code or QR payload</label>
                <input type="text" id="code" placeholder="Paste or scan the ticket hash" class="input-cyber text-sm" autofocus>
            </div>

            <button id="check" class="btn-neon mt-4 w-full rounded-xl px-4 py-3">Validate ticket</button>

            <div id="result" class="mt-4 hidden rounded-xl border border-edge/80 bg-void/60 p-4 text-sm"></div>

            <div class="mt-6 border-t border-edge/70 pt-5">
                <p class="text-xs uppercase tracking-wider text-slate-500">Camera scanner</p>
                <div id="reader" class="mt-3 overflow-hidden rounded-xl border border-edge/80 bg-black"></div>
                <button id="startCam" class="btn-ghost mt-3 rounded-lg px-4 py-2 text-xs uppercase tracking-wider">Start camera</button>
            </div>
        </div>

        <div class="panel p-6">
            <h2 class="font-display text-lg font-bold">How it works</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                <li>Each ticket carries a unique <span class="text-neon">qr_code_hash</span> generated at payment capture.</li>
                <li>The scanner looks the hash up server side; the image is never trusted.</li>
                <li>Used tickets are rejected with the original check-in timestamp.</li>
                <li>Refunded and voided tickets are rejected explicitly.</li>
                <li>Every scan — valid or not — is written to the check-in log.</li>
            </ul>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    const codeInput = document.getElementById('code');
    const listingSelect = document.getElementById('listing');
    const resultBox = document.getElementById('result');

    function render(result, message) {
        resultBox.classList.remove('hidden');
        const tones = {
            valid: ['border-acid/60', 'bg-acid/10', 'text-acid', 'APPROVED'],
            already_used: ['border-amber/60', 'bg-amber/10', 'text-amber', 'ALREADY USED'],
            refunded: ['border-red-500/60', 'bg-red-500/10', 'text-red-300', 'REFUNDED'],
            void: ['border-red-500/60', 'bg-red-500/10', 'text-red-300', 'VOID'],
            wrong_event: ['border-pulse/60', 'bg-pulse/10', 'text-pulse', 'WRONG EVENT'],
            invalid: ['border-red-500/60', 'bg-red-500/10', 'text-red-300', 'INVALID'],
        };
        const [border, bg, text, label] = tones[result] || tones.invalid;
        resultBox.className = 'mt-4 rounded-xl border px-4 py-3 text-sm ' + border + ' ' + bg + ' ' + text;
        resultBox.innerHTML = '<p class="font-display text-lg font-black">' + label + '</p><p class="mt-1">' + (message || '') + '</p>';
    }

    async function checkTicket(value) {
        if (!value) return;
        try {
            const response = await fetch('{{ route('organizer.scanner.check') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ code: value, listing_id: listingSelect.value || null }),
            });
            const data = await response.json();
            render(data.result, data.message);
        } catch (e) {
            render('invalid', 'Scanner could not reach the server.');
        }
    }

    document.getElementById('check').addEventListener('click', () => checkTicket(codeInput.value.trim()));
    codeInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') checkTicket(codeInput.value.trim()); });

    document.getElementById('startCam').addEventListener('click', () => {
        if (typeof Html5Qrcode === 'undefined') {
            render('invalid', 'Camera library unavailable offline. Paste the ticket code instead.');
            return;
        }
        const scanner = new Html5Qrcode('reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            (text) => { checkTicket(text); scanner.stop(); },
            () => {}
        );
    });
</script>
@endpush
