<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Response;

/**
 * Search engine surfaces. Everything published on the marketplace is listed so
 * buyers can find events through Google, Bing and the Novara website.
 */
final class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => now()->toAtomString(), 'priority' => '1.0'],
        ];

        $listings = Listing::query()
            ->published()
            ->orderBy('starts_at')
            ->get(['slug', 'updated_at']);

        foreach ($listings as $listing) {
            $urls[] = [
                'loc' => route('events.show', $listing->slug),
                'lastmod' => ($listing->updated_at ?? now())->toAtomString(),
                'priority' => '0.8',
            ];
        }

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /organizer',
            'Disallow: /my-tickets',
            'Disallow: /my-orders',
            'Disallow: /dashboard',
            'Disallow: /checkout',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
