<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

final class AdminCmsTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_the_admin_dashboard_reports_the_platform_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $this->createPaidOrder($listing, 'TKADMIN00000001');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('TKADMIN00000001')
            ->assertSee('UGX');
    }

    public function test_the_admin_analytics_counts_buyers_separately_from_organizers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->createBuyer();
        $this->createBuyer();
        $this->createOrganizer();

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertViewHas('roleCounts', function (array $counts): bool {
            return $counts['buyer'] === 2
                && $counts['organizer'] === 1
                && $counts['admin'] === 1;
        });
        $response->assertViewHas('totalUsers', 4);
    }

    public function test_the_admin_approvals_queue_lists_the_submitted_mobile_money_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $organizer = $this->createOrganizer(null, [
            'status' => 'pending',
            'approved_at' => null,
            'commission_bps' => null,
            'payout_approved_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('0765866555')
            ->assertSee($organizer->business_name);
    }

    public function test_the_admin_can_set_the_platform_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.settings.commission'), ['commission_percent' => 15])
            ->assertRedirect();

        $this->assertSame('1500', setting('platform_commission_bps'));
    }

    public function test_the_admin_can_suspend_a_buyer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = $this->createBuyer();

        $this->actingAs($admin)
            ->post(route('admin.users.status', $buyer), ['status' => 'suspended', 'role' => 'buyer'])
            ->assertRedirect();

        $this->assertSame('suspended', $buyer->fresh()->status);
    }

    public function test_the_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.users.status', $admin), ['status' => 'active', 'role' => 'buyer'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_the_admin_settles_an_organizer_payout_by_mobile_money(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $organizer = $this->createOrganizer();
        $organizer->wallet()->firstOrFail()->forceFill(['balance_minor' => Money::toMinor(120_000)])->save();

        $this->actingAs($admin)
            ->post(route('admin.settings.payout', $organizer), [
                'amount' => 120_000,
                'method' => 'momo',
                'reference' => 'MM2609180001',
            ])
            ->assertRedirect();

        $this->assertSame(0, (int) $organizer->wallet()->firstOrFail()->balance_minor);
        $this->assertSame(1, $organizer->transactions()->where('type', 'payout')->count());
    }

    public function test_a_non_admin_cannot_reach_the_admin_console(): void
    {
        $buyer = $this->createBuyer();
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $this->createOrganizer($organizerUser);

        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($organizerUser)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($buyer)->post(route('admin.settings.commission'), ['commission_percent' => 0])->assertForbidden();
    }

    public function test_the_dashboard_route_sends_each_role_to_its_own_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = $this->createBuyer();
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $this->createOrganizer($organizerUser);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($organizerUser)->get(route('dashboard'))->assertRedirect(route('organizer.dashboard'));
        $this->actingAs($buyer)->get(route('dashboard'))->assertRedirect(route('buyer.tickets'));
    }

    public function test_the_admin_only_sees_organizers_in_the_approval_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $organizer = $this->createOrganizer(null, ['status' => 'pending']);

        $this->assertInstanceOf(Organizer::class, $organizer);

        $this->actingAs($admin)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee($organizer->business_name);
    }
}
