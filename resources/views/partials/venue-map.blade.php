@php
    /** @var \App\Models\Listing $listing */
    $hasPin = $listing->hasCoordinates();
    $latitude = $hasPin ? (float) $listing->latitude : null;
    $longitude = $hasPin ? (float) $listing->longitude : null;
    $venueLabel = trim(implode(', ', array_filter([$listing->venue, $listing->city])));
    $directionsQuery = $hasPin
        ? $latitude.','.$longitude
        : $venueLabel;
@endphp
<div class="panel overflow-hidden" id="novara-venue-map">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-edge/70 px-4 py-3">
        <div>
            <p class="font-display text-sm font-bold">Venue location</p>
            <p class="text-xs text-slate-500">
                @if($venueLabel)
                    {{ $venueLabel }}
                @else
                    Venue details are published by the organiser.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="novara-map-locate" class="btn-ghost rounded-lg px-3 py-1.5 text-xs">
                Use my location
            </button>
            <a class="btn-neon rounded-lg px-3 py-1.5 text-xs" target="_blank" rel="noopener"
               href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($directionsQuery) }}">
                Get directions
            </a>
        </div>
    </div>

    @if($hasPin)
        <div id="novara-map-canvas" class="h-72 w-full" role="application" aria-label="Map of the event venue"></div>
        <p id="novara-map-status" class="hidden px-4 py-2 text-xs text-slate-400"></p>
    @else
        <div class="grid h-72 w-full place-items-center bg-void/60 px-6 text-center">
            <div>
                <p class="text-sm text-slate-400">This organiser has not pinned the exact spot on the map yet.</p>
                <a class="mt-3 inline-block text-sm font-semibold text-neon underline" target="_blank" rel="noopener"
                   href="https://www.google.com/maps/search/?api=1&query={{ urlencode($venueLabel) }}">
                    Search {{ $venueLabel !== '' ? $venueLabel : 'the venue' }} on Google Maps
                </a>
            </div>
        </div>
    @endif
</div>

@if($hasPin)
    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (function () {
                var canvas = document.getElementById('novara-map-canvas');
                if (!canvas || typeof L === 'undefined') { return; }

                var venue = [{{ $latitude }}, {{ $longitude }}];
                var map = L.map(canvas, { scrollWheelZoom: false }).setView(venue, 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                var pin = L.circleMarker(venue, {
                    radius: 10,
                    color: '#22D3EE',
                    weight: 3,
                    fillColor: '#F472B6',
                    fillOpacity: 0.85
                }).addTo(map);

                pin.bindPopup('<strong>{{ addslashes($listing->title) }}</strong><br>{{ addslashes($venueLabel) }}');

                var locateButton = document.getElementById('novara-map-locate');
                var status = document.getElementById('novara-map-status');

                if (locateButton) {
                    locateButton.addEventListener('click', function () {
                        if (!navigator.geolocation) {
                            status.textContent = 'Your browser does not support location sharing.';
                            status.classList.remove('hidden');
                            return;
                        }

                        status.textContent = 'Requesting your location…';
                        status.classList.remove('hidden');

                        navigator.geolocation.getCurrentPosition(function (position) {
                            var here = [position.coords.latitude, position.coords.longitude];

                            L.circleMarker(here, {
                                radius: 8,
                                color: '#A3E635',
                                weight: 3,
                                fillColor: '#A3E635',
                                fillOpacity: 0.6
                            }).addTo(map).bindPopup('You are here');

                            map.fitBounds(L.latLngBounds([here, venue]).pad(0.6));
                            status.textContent = 'Your position is shown on the map.';
                        }, function () {
                            status.textContent = 'We could not read your location. Check the browser permission and try again.';
                            status.classList.remove('hidden');
                        });
                    });
                }
            })();
        </script>
    @endpush
@endif
