<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class ListingMediaTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_an_organizer_can_attach_a_video_trailer_url_to_a_listing(): void
    {
        $organizer = $this->createOrganizer();

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.store'), [
                'type' => 'event',
                'title' => 'Neon Nights Vol 4',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'max_per_order' => 6,
                'refund_window_hours' => 72,
            ])
            ->assertRedirect();

        $this->assertSame(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            Listing::query()->where('title', 'Neon Nights Vol 4')->firstOrFail()->video_url,
        );
    }

    public function test_a_trailer_url_must_use_http_or_https(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $this->actingAs($organizer->user)
            ->put(route('organizer.listings.update', $listing), [
                'title' => $listing->title,
                'video_url' => 'javascript:alert(1)',
                'starts_at' => $listing->starts_at->toDateTimeString(),
                'max_per_order' => 6,
                'refund_window_hours' => 72,
            ])
            ->assertSessionHasErrors('video_url');

        $this->assertNull($listing->fresh()->video_url);
    }

    public function test_the_embed_allow_list_only_permits_known_video_hosts(): void
    {
        $allowed = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/aBc123xYz' => 'https://www.youtube-nocookie.com/embed/aBc123xYz',
            'https://vimeo.com/123456789' => 'https://player.vimeo.com/video/123456789',
        ];

        foreach ($allowed as $url => $embed) {
            $listing = new Listing(['video_url' => $url]);
            $this->assertSame($embed, $listing->videoEmbed()['url'] ?? null, "Failed on {$url}");
        }

        $rejected = [
            'https://evil.example/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'javascript:alert(1)',
            'https://www.youtube.com/watch?v[]=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=../../evil',
            'not a url at all',
            '',
        ];

        foreach ($rejected as $url) {
            $listing = new Listing(['video_url' => $url]);
            $this->assertNull($listing->videoEmbed(), "Failed on {$url}");
        }
    }

    public function test_the_event_page_embeds_only_allow_listed_trailers(): void
    {
        $organizer = $this->createOrganizer();

        $withTrailer = $this->createListing($organizer, [
            'title' => 'Trailer Ready',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->get(route('events.show', $withTrailer->slug))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $withBadTrailer = $this->createListing($organizer, [
            'title' => 'Hostile Trailer',
            'video_url' => 'https://evil.example/watch?v=dQw4w9WgXcQ',
        ]);

        $this->get(route('events.show', $withBadTrailer->slug))
            ->assertOk()
            ->assertDontSee('evil.example', false)
            ->assertDontSee('youtube-nocookie.com', false);
    }
}
