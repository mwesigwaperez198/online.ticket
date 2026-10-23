<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\User;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class OrganizerPortalTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_mobile_navigation_exposes_browse_tickets_and_organizer_dashboard(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $this->createOrganizer($organizerUser);

        $this->actingAs($organizerUser)
            ->get(route('organizer.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Mobile navigation"', false)
            ->assertSee('Browse')
            ->assertSee('My Tickets')
            ->assertSee('Dashboard');
    }

    public function test_an_organizer_only_sees_their_own_listings(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);

        $ownListing = $this->createListing($organizer, ['title' => 'Neon Symphony 2026']);

        $otherOrganizer = $this->createOrganizer();
        $otherListing = $this->createListing($otherOrganizer, ['title' => 'Someone Elses Concert']);

        $this->actingAs($organizerUser)
            ->get(route('organizer.listings.index'))
            ->assertOk()
            ->assertSee('Neon Symphony 2026')
            ->assertDontSee('Someone Elses Concert');

        $this->assertSame($organizer->id, $ownListing->organizer_id);
        $this->assertNotSame($otherListing->organizer_id, $organizer->id);
    }

    public function test_an_organizer_dashboard_only_reports_their_own_sales(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);

        $ownListing = $this->createListing($organizer);
        $otherListing = $this->createListing($this->createOrganizer());

        $this->createPaidOrder($ownListing, 'TKMINE0000000001');
        $this->createPaidOrder($otherListing, 'TKOTHER000000001');

        $this->actingAs($organizerUser)
            ->get(route('organizer.dashboard'))
            ->assertOk()
            ->assertSee('TKMINE0000000001')
            ->assertDontSee('TKOTHER000000001');
    }

    public function test_an_organizer_cannot_edit_a_listing_owned_by_someone_else(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $this->createOrganizer($organizerUser);

        $otherOrganizer = $this->createOrganizer();
        $otherListing = $this->createListing($otherOrganizer);

        $this->actingAs($organizerUser)
            ->get(route('organizer.listings.edit', $otherListing))
            ->assertForbidden();

        $this->actingAs($organizerUser)
            ->post(route('organizer.listings.publish', $otherListing))
            ->assertForbidden();

        $this->assertSame('published', $otherListing->fresh()->status);
    }

    public function test_an_organizer_submits_their_mobile_money_payout_details(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser, [
            'payout_momo_number' => null,
            'payout_provider' => null,
            'payout_approved_at' => null,
        ]);

        $this->actingAs($organizerUser)
            ->post(route('organizer.payout-settings.update'), [
                'payout_name' => 'DA-VELA',
                'payout_provider' => 'mtn',
                'payout_momo_number' => '0765866555',
                'instagram' => 'https://www.instagram.com/novara_tech_uganda',
                'whatsapp' => '+256765866555',
                'website' => 'https://novara-tech-africa.kesug.com',
            ])
            ->assertRedirect(route('organizer.payout-settings.edit'));

        $organizer->refresh();

        $this->assertSame('0765866555', $organizer->payout_momo_number);
        $this->assertSame('mtn', $organizer->payout_provider);
        $this->assertNotNull($organizer->payout_submitted_at);
        $this->assertNull($organizer->payout_approved_at);
        $this->assertTrue($organizer->isAwaitingPayoutApproval());
        $this->assertSame(
            ['Instagram', 'WhatsApp', 'Website'],
            array_keys($organizer->socialLinks())
        );
    }

    public function test_changing_the_payout_number_requires_a_new_approval(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);

        $this->assertNotNull($organizer->payout_approved_at);

        $this->actingAs($organizerUser)
            ->post(route('organizer.payout-settings.update'), [
                'payout_name' => 'DA-VELA',
                'payout_provider' => 'airtel',
                'payout_momo_number' => '0756123456',
            ])
            ->assertRedirect();

        $organizer->refresh();

        $this->assertSame('0756123456', $organizer->payout_momo_number);
        $this->assertNull($organizer->payout_approved_at);
    }

    public function test_a_payout_number_outside_uganda_is_rejected(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser, [
            'payout_momo_number' => null,
            'payout_provider' => null,
        ]);

        $this->actingAs($organizerUser)
            ->post(route('organizer.payout-settings.update'), [
                'payout_name' => 'DA-VELA',
                'payout_provider' => 'mtn',
                'payout_momo_number' => '0240000003',
            ])
            ->assertSessionHasErrors('payout_momo_number');

        $this->assertNull($organizer->fresh()->payout_momo_number);
    }

    public function test_an_organizer_cannot_open_the_admin_area(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $this->createOrganizer($organizerUser);

        $this->actingAs($organizerUser)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($organizerUser)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($organizerUser)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_the_admin_approves_the_organizer_at_zero_commission(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser, [
            'commission_bps' => null,
            'status' => 'pending',
            'approved_at' => null,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.approvals.update', $organizer), [
                'status' => 'approved',
                'commission_percent' => 0,
                'payout_momo_number' => '0765866555',
                'payout_provider' => 'mtn',
            ])
            ->assertRedirect();

        $organizer->refresh();

        $this->assertSame('approved', $organizer->status);
        $this->assertSame(0, $organizer->commission_bps);
        $this->assertSame(0, $organizer->effectiveCommissionBps());
        $this->assertNotNull($organizer->payout_approved_at);

        // A sale on an approved 0% organizer credits the full amount.
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, ['price_minor' => Money::toMinor(250_000)]);

        $buyer = $this->createBuyer();

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        app(DemoMomoGateway::class)->approve($order->tx_ref);

        $this->actingAs($buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $order->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame(0, $order->commission_bps_applied);
        $this->assertSame(0, $order->commission_minor);
        $this->assertSame(Money::toMinor(250_000), $organizer->wallet()->firstOrFail()->balance_minor);
    }

    public function test_the_organizer_scanner_checks_a_ticket_in_once(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);

        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);

        $buyer = $this->createBuyer();

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        app(DemoMomoGateway::class)->approve($order->tx_ref);
        $this->actingAs($buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($organizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'valid']);

        $ticket->refresh();

        $this->assertSame('used', $ticket->status);
        $this->assertNotNull($ticket->used_at);
        $this->assertSame($organizerUser->id, $ticket->used_by);

        $this->assertSame(1, TicketScan::query()->where('result', 'valid')->count());

        $this->actingAs($organizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'already_used']);
    }

    public function test_the_organizer_scanner_rejects_a_ticket_from_another_event(): void
    {
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);

        $otherOrganizer = $this->createOrganizer();
        $otherListing = $this->createListing($otherOrganizer);
        $otherTier = $this->createTicketType($otherListing);

        $buyer = $this->createBuyer();

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $otherListing->id,
            'ticket_type_id' => $otherTier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        app(DemoMomoGateway::class)->approve($order->tx_ref);
        $this->actingAs($buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($organizerUser)
            ->postJson(route('organizer.scanner.check'), ['code' => $ticket->qr_code_hash])
            ->assertOk()
            ->assertJson(['result' => 'wrong_event']);

        $this->assertSame('issued', $ticket->fresh()->status);
        $this->assertSame(1, TicketScan::query()->where('result', 'wrong_event')->count());
    }
}
