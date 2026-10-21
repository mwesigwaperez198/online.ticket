<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class MarketplaceHomeTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_featured_listings_lead_the_picks_row_and_fallback_fills_with_the_soonest_events(): void
    {
        $organizer = $this->createOrganizer();

        $soonest = $this->createListing($organizer, [
            'title' => 'Soonest Kampala Event',
            'starts_at' => now()->addDays(2),
        ]);
        $featured = $this->createListing($organizer, [
            'title' => 'Curated Headliner',
            'starts_at' => now()->addDays(30),
            'featured' => true,
        ]);
        $later = $this->createListing($organizer, [
            'title' => 'Later Event',
            'starts_at' => now()->addDays(60),
        ]);

        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Our Picks', $content);
        $this->assertNotFalse(strpos($content, 'Curated Headliner'));
        $this->assertNotFalse(strpos($content, 'Soonest Kampala Event'));

        // The curated pick leads the row even though it is not the soonest.
        $this->assertLessThan(
            strpos($content, 'Soonest Kampala Event'),
            strpos($content, 'Curated Headliner'),
        );
        $this->assertTrue($featured->featured);
        $this->assertFalse($soonest->fresh()->featured);
        $this->assertFalse($later->fresh()->featured);
    }

    public function test_draft_and_pending_events_never_reach_the_home_page(): void
    {
        $organizer = $this->createOrganizer();

        $this->createListing($organizer, ['title' => 'Hidden Draft', 'status' => 'draft', 'published_at' => null]);
        $this->createListing($organizer, ['title' => 'Hidden Pending', 'status' => 'pending', 'published_at' => null]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Hidden Draft')
            ->assertDontSee('Hidden Pending');
    }

    public function test_load_more_returns_the_next_page_of_cards_as_json(): void
    {
        $organizer = $this->createOrganizer();

        foreach (range(1, 10) as $index) {
            $this->createListing($organizer, [
                'title' => "Load More Event {$index}",
                'starts_at' => now()->addDays($index),
            ]);
        }

        $first = $this->loadMore(['page' => 1]);
        $first->assertOk()->assertJson(['page' => 1, 'last' => false, 'next' => 2]);
        $this->assertStringContainsString('Load More Event 1', (string) $first->json('html'));
        $this->assertStringNotContainsString('Load More Event 10', (string) $first->json('html'));

        $second = $this->loadMore(['page' => 2]);
        $second->assertOk()->assertJson(['page' => 2, 'last' => true, 'next' => null]);
        $this->assertStringContainsString('Load More Event 10', (string) $second->json('html'));
    }

    public function test_load_more_preserves_the_search_and_type_filters(): void
    {
        $organizer = $this->createOrganizer();

        $this->createListing($organizer, ['title' => 'Kampala Jazz Fest']);
        $this->createListing($organizer, ['title' => 'Neon Rocks Reunion']);

        $filtered = $this->loadMore(['page' => 1, 'q' => 'jazz', 'type' => 'event']);
        $filtered->assertOk()->assertJson(['page' => 1]);
        $this->assertStringContainsString('Kampala Jazz Fest', (string) $filtered->json('html'));
        $this->assertStringNotContainsString('Neon Rocks Reunion', (string) $filtered->json('html'));
    }

    public function test_an_admin_can_feature_and_unfeature_a_pick_from_the_cms(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.listings.feature', $listing))
            ->assertRedirect();
        $this->assertTrue($listing->fresh()->featured);

        $this->actingAs($admin)
            ->post(route('admin.listings.feature', $listing))
            ->assertRedirect();
        $this->assertFalse($listing->fresh()->featured);
    }

    public function test_a_buyer_cannot_curate_the_picks_row(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $this->actingAs($this->createBuyer())
            ->post(route('admin.listings.feature', $listing))
            ->assertForbidden();

        $this->assertFalse($listing->fresh()->featured);
    }

    private function loadMore(array $query): TestResponse
    {
        return $this->get(route('events.load-more', $query));
    }
}
