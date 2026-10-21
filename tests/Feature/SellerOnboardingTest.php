<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class SellerOnboardingTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_a_buyer_can_open_the_seller_onboarding_page(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)
            ->get(route('seller.onboarding'))
            ->assertOk()
            ->assertSee('Start selling on Novara');
    }

    public function test_a_buyer_becomes_a_seller_and_gains_the_seller_workspace(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)
            ->post(route('seller.onboarding.store'), ['business_name' => 'Kampala Nights'])
            ->assertRedirect(route('organizer.dashboard'));

        $organizer = Organizer::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame('Kampala Nights', $organizer->business_name);
        $this->assertSame('pending', $organizer->status);
        $this->assertSame('kampala-nights', $organizer->slug);
        $this->assertSame('organizer', $buyer->fresh()->role);

        // The role string is not the real gate: holding an organizer profile
        // is what unlocks the workspace.
        $this->actingAs($buyer->fresh())
            ->get(route('organizer.dashboard'))
            ->assertOk();
    }

    public function test_the_onboarding_requires_a_business_name(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)
            ->from(route('seller.onboarding'))
            ->post(route('seller.onboarding.store'), ['business_name' => ''])
            ->assertRedirect(route('seller.onboarding'))
            ->assertSessionHasErrors('business_name');

        $this->assertSame(0, Organizer::query()->count());
    }

    public function test_submitting_onboarding_twice_keeps_a_single_seller_profile(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)
            ->post(route('seller.onboarding.store'), ['business_name' => 'First Name'])
            ->assertRedirect(route('organizer.dashboard'));

        // A second submit (or a bookmarked form) must not fork the profile.
        $this->actingAs($buyer->fresh())
            ->post(route('seller.onboarding.store'), ['business_name' => 'Second Name'])
            ->assertRedirect(route('organizer.dashboard'));

        $this->assertSame(1, Organizer::query()->count());
        $this->assertSame('First Name', Organizer::query()->firstOrFail()->business_name);
    }

    public function test_onboarding_redirects_away_when_the_profile_already_exists(): void
    {
        $organizer = $this->createOrganizer();

        $this->actingAs($organizer->user)
            ->get(route('seller.onboarding'))
            ->assertRedirect(route('organizer.dashboard'));
    }

    public function test_a_buyer_with_a_seller_profile_passes_the_seller_gate_but_a_plain_buyer_does_not(): void
    {
        // Capability-based access: the profile, not the role string, opens the door.
        $organizer = $this->createOrganizer($this->createBuyer());
        $organizer->user->forceFill(['role' => 'buyer'])->save();

        $this->actingAs($organizer->user)
            ->get(route('organizer.listings.create'))
            ->assertOk();

        $this->actingAs($this->createBuyer())
            ->get(route('organizer.dashboard'))
            ->assertForbidden();
    }

    public function test_the_seller_onboarding_is_closed_to_guests(): void
    {
        $this->get(route('seller.onboarding'))->assertRedirect(route('login'));
        $this->post(route('seller.onboarding.store'), ['business_name' => 'Walk In'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Organizer::query()->count());
    }

    public function test_an_admin_is_not_offered_seller_onboarding(): void
    {
        $admin = $this->createBuyer(['role' => 'admin', 'email' => 'admin.onboarding@example.test']);

        $this->actingAs($admin)
            ->get(route('seller.onboarding'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->post(route('seller.onboarding.store'), ['business_name' => 'Admin Side Hustle'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, Organizer::query()->count());
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_the_navigation_offers_the_seller_path_to_buyers_only(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($buyer)
            ->get(route('buyer.tickets'))
            ->assertOk()
            ->assertSee(route('seller.onboarding'), false)
            ->assertSee('Start selling', false);

        $organizer = $this->createOrganizer();

        $this->actingAs($organizer->user)
            ->get(route('buyer.tickets'))
            ->assertOk()
            ->assertSee(route('organizer.dashboard'), false)
            ->assertDontSee('Start selling', false);
    }
}
