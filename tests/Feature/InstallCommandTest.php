<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_platform_and_provisions_the_configured_administrator(): void
    {
        config([
            'ticketing.admin.email' => 'owner@example.test',
            'ticketing.admin.password' => 'sup3r-secret',
        ]);

        $this->artisan('ticketing:install')->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('sup3r-secret', $admin->password));
        $this->assertSame(2, Listing::query()->count());
    }

    public function test_it_never_ships_the_default_password(): void
    {
        config([
            'ticketing.admin.email' => 'owner@example.test',
            'ticketing.admin.password' => '',
        ]);

        $this->artisan('ticketing:install')->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_it_preserves_sales_data_when_it_runs_again(): void
    {
        config([
            'ticketing.admin.email' => 'owner@example.test',
            'ticketing.admin.password' => 'sup3r-secret',
        ]);

        $this->artisan('ticketing:install')->assertSuccessful();

        $tier = TicketType::query()->where('name', 'Early Bird')->firstOrFail();
        $tier->increment('sold_count', 5);
        $soldBefore = $tier->fresh()?->sold_count;

        $this->artisan('ticketing:install')->assertSuccessful();

        $this->assertSame($soldBefore, $tier->fresh()?->sold_count);
    }

    public function test_it_synchronises_the_administrator_password_with_the_configuration(): void
    {
        config([
            'ticketing.admin.email' => 'owner@example.test',
            'ticketing.admin.password' => 'first-password',
        ]);

        $this->artisan('ticketing:install')->assertSuccessful();

        config(['ticketing.admin.password' => 'rotated-password']);

        $this->artisan('ticketing:install')->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('rotated-password', $admin->password));
        $this->assertFalse(Hash::check('first-password', $admin->password));
    }
}
