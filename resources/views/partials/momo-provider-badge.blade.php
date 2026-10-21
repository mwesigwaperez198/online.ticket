@props(['providerKey' => 'mtn', 'label' => null])

@php
    // The official MTN MoMo mark, shipped in-repo so the payment screens never
    // depend on a third party CDN. It carries an alpha channel, so it is placed
    // on a light chip to stay legible against the dark cyberpunk surfaces.
    $isMtn = $providerKey === 'mtn';
    $text = $label ?? ($isMtn ? 'MTN Mobile Money' : 'Airtel Money');
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 rounded-xl border border-edge/80 bg-white/95 px-3 py-2']) }}>
    @if($isMtn)
        <img src="{{ asset('images/mtn-momo.png') }}" alt="MTN MoMo" class="h-8 w-auto" width="628" height="294" loading="lazy">
    @else
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#E4002B] font-display text-xs font-black text-white">A</span>
    @endif
    <span class="text-sm font-semibold text-slate-800">{{ $text }}</span>
</span>
