<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\TicketingNotice;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentProcessor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\Fixtures\RecordingMomoGateway;
use Tests\TestCase;

final class AccountNotificationsAndRecordsTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_user_can_update_profile_and_password_with_current_password_confirmation(): void
    {
        $user = $this->createBuyer();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Buyer',
            'email' => 'updated@example.test',
            'phone' => '0771111111',
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('Updated Buyer', $user->fresh()->name);
        $this->assertSame('updated@example.test', $user->fresh()->email);
        $this->assertTrue(password_verify('new-secure-password', $user->fresh()->password));
    }

    public function test_paid_order_receipt_is_downloadable_only_after_payment(): void
    {
        Notification::fake();
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer, ['title' => 'Receipt Concert']);
        $tier = $this->createTicketType($listing);
        $buyer = $this->createBuyer();
        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => $buyer->name,
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->actingAs($buyer)->get(route('buyer.orders.receipt', $order))
            ->assertRedirect(route('buyer.orders.show', $order))
            ->assertSessionHas('error');

        $gateway->approve($order->tx_ref);
        $this->actingAs($buyer)->get(route('checkout.return', ['reference' => $order->tx_ref]));

        $this->actingAs($buyer)->get(route('buyer.orders.receipt', $order))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="receipt-'.$order->tx_ref.'.html"')
            ->assertSee('PAYMENT RECEIPT')
            ->assertSee('Receipt Concert')
            ->assertSee($order->tx_ref);

        Notification::assertSentTo($buyer, TicketingNotice::class);
        Notification::assertSentTo($organizer->user, TicketingNotice::class);
    }

    public function test_admin_can_archive_and_restore_user_without_deleting_order_history(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = $this->createBuyer();
        $listing = $this->createListing($this->createOrganizer());
        $order = $this->createPaidOrder($listing, 'TKARCHIVE00000001');
        $order->forceFill(['user_id' => $buyer->id])->save();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $buyer->id))->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $buyer->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $buyer->id]);
        $this->assertSame($buyer->id, $order->fresh()->user->id);

        $this->actingAs($admin)->post(route('admin.users.restore', $buyer->id))->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $buyer->id]);
        $this->assertSame('active', $buyer->fresh()->status);
    }

    public function test_admin_account_status_update_can_activate_a_suspended_organizer(): void
    {
        Notification::fake();
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        /** @var User $organizerUser */
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'suspended']);
        $organizer = $this->createOrganizer($organizerUser, ['status' => 'suspended']);

        $this->actingAs($admin)
            ->post(route('admin.approvals.update', $organizer), [
                'status' => 'approved',
                'payout_momo_number' => '0765866555',
                'payout_provider' => 'mtn',
            ])
            ->assertRedirect();

        $this->assertSame('active', $organizerUser->fresh()->status);
        $this->assertSame('approved', $organizer->fresh()->status);
        Notification::assertSentTo($organizerUser, TicketingNotice::class);
    }

    public function test_archived_organizer_listings_restore_to_their_previous_status(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        /** @var User $organizerUser */
        $organizerUser = User::factory()->create(['role' => 'organizer', 'status' => 'active']);
        $organizer = $this->createOrganizer($organizerUser);
        $listing = $this->createListing($organizer, ['status' => 'published']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $organizerUser->id))->assertRedirect();
        $this->assertSoftDeleted('listings', ['id' => $listing->id]);

        $this->actingAs($admin)->post(route('admin.users.restore', $organizerUser->id))->assertRedirect();
        $this->assertNotSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertSame('published', $listing->fresh()->status);
    }

    public function test_admin_sends_password_reset_link_instead_of_exposing_a_temporary_password(): void
    {
        Notification::fake();
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = $this->createBuyer();

        $this->actingAs($admin)
            ->post(route('admin.users.password-reset', $buyer->id))
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($buyer, ResetPassword::class);
    }

    public function test_admin_can_archive_event_without_deleting_existing_orders(): void
    {
        Notification::fake();
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $listing = $this->createListing($this->createOrganizer());
        $order = $this->createPaidOrder($listing, 'TKLISTARCHIVE001');

        $this->actingAs($admin)
            ->delete(route('admin.listings.destroy', $listing))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'listing_id' => $listing->id]);
        $this->assertSame($listing->id, $order->fresh()->listing->id);
        Notification::assertSentTo($order->user, TicketingNotice::class);
    }

    public function test_failed_payment_releases_held_inventory_and_emails_the_buyer(): void
    {
        Notification::fake();
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);
        $buyer = $this->createBuyer();
        $gateway = new RecordingMomoGateway;
        $this->app->instance(MomoGateway::class, $gateway);

        $this->actingAs($buyer)->post(route('checkout.store'), [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => $buyer->name,
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        app(PaymentProcessor::class)->markFailed($order, 'Provider declined');

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(0, (int) $tier->fresh()->held_count);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
        Notification::assertSentTo($buyer, TicketingNotice::class);
    }

    public function test_listing_submission_and_payment_send_email_notices(): void
    {
        Notification::fake();
        $organizer = $this->createOrganizer();
        $adminEmail = config('ticketing.admin.email');

        $this->actingAs($organizer->user)->post(route('organizer.listings.store'), [
            'intent' => 'submit',
            'type' => 'event',
            'title' => 'Email Notice Event',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'max_per_order' => 6,
            'refund_window_hours' => 72,
            'ticket_types' => [['name' => 'General', 'price' => '25000', 'capacity' => '100']],
            'cover_image' => UploadedFile::fake()->image('banner.png'),
        ])->assertRedirect();

        Notification::assertSentTo($organizer->user, TicketingNotice::class);
        Notification::assertSentOnDemand(TicketingNotice::class);
        $this->assertNotEmpty($adminEmail);
    }
}
