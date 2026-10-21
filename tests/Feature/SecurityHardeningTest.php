<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentGateway\MtnMomoGateway;
use App\Services\PaymentGateway\UnavailableMomoGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

/**
 * The production safety rails: rate limits, webhook authentication, upload
 * validation, database indexes, route isolation and the payment gateway
 * guard that keeps a production deployment from ever settling a mocked
 * payment.
 */
final class SecurityHardeningTest extends TestCase
{
    use InteractsWithMarketplace;
    use RefreshDatabase;

    public function test_the_checkout_route_is_throttled(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing);
        $buyer = $this->createBuyer();

        $payload = [
            'listing_id' => $listing->id,
            'ticket_type_id' => $tier->id,
            'quantity' => 1,
            'buyer_name' => 'Ivan Kaggwa',
            'buyer_email' => $buyer->email,
            'buyer_phone' => '0771234567',
            'momo_provider' => 'mtn',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($buyer)->post(route('checkout.store'), $payload)->assertRedirect();
        }

        $this->actingAs($buyer)->post(route('checkout.store'), $payload)->assertStatus(429);
    }

    public function test_the_webhook_rejects_a_callback_without_a_valid_signature(): void
    {
        config(['ticketing.paystack.secret_key' => 'sk_test_secret']);

        $this->postJson(route('api.v1.momo.webhook'), [
            'event' => 'charge.success',
            'data' => ['status' => 'success', 'reference' => 'TKFAKE', 'amount' => 100, 'currency' => 'UGX'],
        ])->assertStatus(401);
    }

    public function test_the_webhook_reports_a_missing_provider_secret(): void
    {
        config([
            'ticketing.paystack.secret_key' => '',
            'ticketing.momo.webhook_providers' => ['paystack'],
        ]);

        $this->postJson(route('api.v1.momo.webhook'), [
            'event' => 'charge.success',
            'data' => ['status' => 'success', 'reference' => 'TKFAKE', 'amount' => 100, 'currency' => 'UGX'],
        ])->assertStatus(503);
    }

    public function test_a_cover_upload_that_is_not_an_image_is_rejected(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $file = UploadedFile::fake()->createWithContent('payload.jpg', '<?php echo "not an image";');

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.cover', $listing), ['cover_image' => $file])
            ->assertSessionHasErrors('cover_image');

        $this->assertNull($listing->fresh()->cover_image);
    }

    public function test_a_cover_upload_larger_than_two_megabytes_is_rejected(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $path = tempnam(sys_get_temp_dir(), 'novara').'.png';
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $path);
        imagedestroy($image);

        // Pad the file past the two megabyte limit while keeping it a PNG.
        file_put_contents($path, str_repeat("\0", 2_200_000), FILE_APPEND);

        $file = new UploadedFile($path, 'cover.png', 'image/png', null, true);

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.cover', $listing), ['cover_image' => $file])
            ->assertSessionHasErrors('cover_image');

        $this->assertNull($listing->fresh()->cover_image);

        @unlink($path);
    }

    public function test_a_valid_cover_upload_is_stored(): void
    {
        Storage::fake('public');

        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $path = tempnam(sys_get_temp_dir(), 'novara').'.png';
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $path);
        imagedestroy($image);

        $file = new UploadedFile($path, 'cover.png', 'image/png', null, true);

        $this->actingAs($organizer->user)
            ->post(route('organizer.listings.cover', $listing), ['cover_image' => $file])
            ->assertRedirect();

        $listing->refresh();

        $this->assertNotNull($listing->cover_image);
        $this->assertStringEndsWith('.png', (string) $listing->cover_image);
        Storage::disk('public')->assertExists($listing->cover_image);

        @unlink($path);
    }

    public function test_the_production_indexes_exist(): void
    {
        $expected = [
            'organizers' => ['organizers_user_id_index'],
            'listings' => ['listings_organizer_id_index', 'listings_organizer_id_status_index'],
            'orders' => ['orders_user_id_index', 'orders_listing_id_status_index', 'orders_gateway_reference_index'],
            'order_items' => ['order_items_order_id_index'],
            'tickets' => ['tickets_order_id_index', 'tickets_holder_email_index'],
            'payments' => ['payments_order_id_index'],
            'wallet_transactions' => ['wallet_transactions_order_id_index', 'wallet_transactions_wallet_id_index'],
            'refunds' => ['refunds_order_id_index'],
        ];

        foreach ($expected as $table => $indexes) {
            $present = array_column(Schema::getIndexes($table), 'name');

            foreach ($indexes as $index) {
                $this->assertContains($index, $present, "Missing index {$index} on {$table}.");
            }
        }
    }

    public function test_the_organizer_and_admin_routes_are_isolated(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'admin') && ! str_starts_with($uri, 'organizer')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, "{$uri} is not authenticated.");
            $this->assertTrue(
                collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'role:')),
                "{$uri} does not enforce a role.",
            );
        }
    }

    public function test_the_money_routes_carry_their_protection(): void
    {
        $webhook = Route::getRoutes()->getByName('api.v1.momo.webhook');

        $this->assertNotNull($webhook);
        $this->assertContains('webhook.signature', $webhook->gatherMiddleware());
        $this->assertTrue(
            collect($webhook->gatherMiddleware())->contains(fn (string $item): bool => str_starts_with($item, 'throttle:')),
        );

        $checkout = Route::getRoutes()->getByName('checkout.store');

        $this->assertNotNull($checkout);
        $this->assertTrue(
            collect($checkout->gatherMiddleware())->contains(fn (string $item): bool => str_starts_with($item, 'throttle:')),
        );
    }

    public function test_a_production_deployment_never_binds_the_demo_gateway(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(MomoGateway::class);

        config([
            'ticketing.momo.mode' => 'live',
            'ticketing.momo.gateway' => 'paystack',
            'ticketing.paystack.secret_key' => '',
        ]);

        $gateway = $this->app->make(MomoGateway::class);

        $this->assertInstanceOf(UnavailableMomoGateway::class, $gateway);
        $this->assertNotInstanceOf(DemoMomoGateway::class, $gateway);
    }

    public function test_a_production_deployment_refuses_a_non_live_payment_mode(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(MomoGateway::class);

        config([
            'ticketing.momo.mode' => 'demo',
            'ticketing.momo.gateway' => 'paystack',
            'ticketing.paystack.secret_key' => 'sk_test_secret',
        ]);

        $this->assertInstanceOf(UnavailableMomoGateway::class, $this->app->make(MomoGateway::class));
    }

    public function test_the_mtn_gateway_is_bound_when_the_subscription_keys_are_configured(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(MomoGateway::class);

        config([
            'ticketing.momo.mode' => 'live',
            'ticketing.momo.gateway' => 'mtn',
            'ticketing.momo.mtn.primary_key' => 'primary-key',
            'ticketing.momo.mtn.secondary_key' => 'secondary-key',
        ]);

        $this->assertInstanceOf(MtnMomoGateway::class, $this->app->make(MomoGateway::class));
    }

    public function test_the_mtn_gateway_is_refused_without_a_subscription_key(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(MomoGateway::class);

        config([
            'ticketing.momo.mode' => 'live',
            'ticketing.momo.gateway' => 'mtn',
            'ticketing.momo.mtn.primary_key' => '',
            'ticketing.momo.mtn.secondary_key' => '',
        ]);

        $this->assertInstanceOf(UnavailableMomoGateway::class, $this->app->make(MomoGateway::class));
    }

    public function test_a_buyer_cannot_read_another_buyers_order(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);
        $tier = $this->createTicketType($listing, ['price_minor' => Money::toMinor(50_000)]);

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

        $this->actingAs($this->createBuyer())
            ->get(route('buyer.orders.show', $order))
            ->assertForbidden();
    }

    public function test_an_organizer_cannot_read_another_organizers_listing(): void
    {
        $organizer = $this->createOrganizer();
        $listing = $this->createListing($organizer);

        $other = $this->createOrganizer();

        $this->actingAs($other->user)
            ->get(route('organizer.listings.edit', $listing))
            ->assertForbidden();
    }
}
