<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class ListingReviewFlowTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_an_organizer_can_save_a_draft_with_its_banner_and_submit_it_from_the_form(): void
    {
        Storage::fake('public');
        $organizer = $this->createOrganizer();

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.store'), [
                'intent' => 'submit',
                'type' => 'event',
                'title' => 'Summer Sounds',
                'description' => 'Live music by the lake.',
                'venue' => 'Lake Grounds',
                'city' => 'Kampala',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'max_per_order' => 6,
                'refund_window_hours' => 72,
                'ticket_types' => [
                    ['name' => 'General', 'price' => '25000', 'capacity' => '200'],
                    ['name' => '', 'price' => '', 'capacity' => ''],
                ],
                'cover_image' => UploadedFile::fake()->image('summer.png'),
                'gallery_images' => [
                    UploadedFile::fake()->image('stage.png'),
                    UploadedFile::fake()->image('crowd.png'),
                ],
            ])
            ->assertRedirect();

        $listing = Listing::query()->where('title', 'Summer Sounds')->firstOrFail();
        $this->assertSame('pending', $listing->status);
        $this->assertNotNull($listing->cover_image);
        $this->assertSame(1, $listing->ticketTypes()->count());
        $this->assertSame(2, $listing->images()->count());
        foreach ($listing->images as $image) {
            $this->assertTrue(Storage::disk('public')->exists($image->path));
        }
        $this->assertTrue(Storage::disk('public')->exists($listing->cover_image));
    }

    public function test_customers_can_view_event_gallery_and_organizers_can_remove_photos(): void
    {
        Storage::fake('public');
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer, ['title' => 'Gallery Event']);
        $image = $listing->images()->create([
            'path' => 'listing-galleries/'.$listing->id.'/stage.jpg',
            'sort_order' => 0,
        ]);
        Storage::disk('public')->put($image->path, 'image data');

        $this->get(route('events.show', $listing->slug))
            ->assertOk()
            ->assertSee('Event photos')
            ->assertSee($image->url(), false);

        $this->actingAs($organizer->user)
            ->delete(route('organizer.listings.images.destroy', [$listing, $image]))
            ->assertRedirect();

        $this->assertDatabaseMissing('listing_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_submitting_without_a_banner_keeps_the_listing_as_a_draft(): void
    {
        $organizer = $this->createOrganizer();

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.store'), [
                'intent' => 'submit',
                'type' => 'event',
                'title' => 'Draft Without Banner',
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'max_per_order' => 6,
                'refund_window_hours' => 72,
                'ticket_types' => [['name' => 'General', 'price' => '25000', 'capacity' => '200']],
            ])
            ->assertRedirect();

        $listing = Listing::query()->where('title', 'Draft Without Banner')->firstOrFail();
        $this->assertSame('draft', $listing->status);
        $this->assertNull($listing->cover_image);
    }

    public function test_admin_review_shows_the_banner_and_merchant_and_saves_commission_with_approval(): void
    {
        Storage::fake('public');
        $merchantUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($merchantUser, [
            'instagram' => 'https://instagram.com/example-events',
            'commission_bps' => 2000,
        ]);
        $listing = $this->createListing($organizer, [
            'title' => 'Review This Event',
            'status' => 'pending',
            'published_at' => null,
            'cover_image' => 'covers/review-event.png',
        ]);
        Storage::disk('public')->put($listing->cover_image, 'fake image bytes');
        $galleryImage = $listing->images()->create([
            'path' => 'listing-galleries/'.$listing->id.'/stage.jpg',
            'sort_order' => 0,
        ]);
        Storage::disk('public')->put($galleryImage->path, 'gallery image bytes');
        $this->createTicketType($listing, ['name' => 'Balcony', 'capacity' => 120]);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.listings.review', $listing))
            ->assertOk()
            ->assertSee('Review This Event')
            ->assertSee('DA-VELA')
            ->assertSee('Payout account')
            ->assertSee('Balcony')
            ->assertSee($galleryImage->url(), false)
            ->assertSee($listing->coverImageUrl(), false);

        $this->actingAs($admin)
            ->post(route('admin.listings.moderate', $listing), [
                'status' => 'published',
                'commission_percent' => '7.5',
            ])
            ->assertRedirect();

        $this->assertSame('published', $listing->fresh()->status);
        $this->assertSame(750, $organizer->fresh()->commission_bps);
    }
}
