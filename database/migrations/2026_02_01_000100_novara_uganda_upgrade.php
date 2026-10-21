<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table): void {
            $table->string('instagram', 190)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('facebook', 190)->nullable();
            $table->string('website', 190)->nullable();
            $table->timestamp('payout_submitted_at')->nullable();
            $table->timestamp('payout_approved_at')->nullable();
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->string('venue_address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable();
        });

        $this->switchCurrencyToUgandanShilling();
        $this->relocateLegacyDemoContent();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_login_at');
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn(['venue_address', 'latitude', 'longitude']);
        });

        Schema::table('organizers', function (Blueprint $table): void {
            $table->dropColumn([
                'instagram', 'whatsapp', 'facebook', 'website',
                'payout_submitted_at', 'payout_approved_at',
            ]);
        });
    }

    /**
     * The platform moved from Ghana cedis to Ugandan shillings. Stored amounts
     * keep their minor unit scale, only the currency identity changes.
     */
    private function switchCurrencyToUgandanShilling(): void
    {
        foreach (['ticket_types', 'orders', 'payments', 'wallets'] as $table) {
            DB::table($table)->where('currency', 'GHS')->update(['currency' => 'UGX']);
        }

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->string('currency', 3)->default('UGX')->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('currency', 3)->default('UGX')->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('currency', 3)->default('UGX')->change();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('currency', 3)->default('UGX')->change();
        });

        DB::table('settings')->where('key', 'currency')->update(['value' => 'UGX']);
    }

    /**
     * The first release of the platform shipped with Ghanaian demo content.
     * The same listings stay online — their URLs must not break — but they
     * are relocated to Uganda and repriced in shillings.
     */
    private function relocateLegacyDemoContent(): void
    {
        DB::table('organizers')->where('slug', 'accra-nights-collective')->update([
            'business_name' => 'DA-VELA',
            'payout_name' => 'DA-VELA',
            'payout_provider' => 'mtn',
            'payout_momo_number' => '0765866555',
            'commission_bps' => 0,
            'status' => 'approved',
            'payout_submitted_at' => now(),
            'payout_approved_at' => now(),
            'updated_at' => now(),
        ]);

        $listings = [
            'neon-nights-vol-3' => [
                'venue' => 'Lugogo Cricket Oval',
                'venue_address' => 'Plot 30, Lugogo Cricket Oval, Kampala',
                'city' => 'Kampala',
                'region' => 'Central Region',
                'latitude' => 0.3156,
                'longitude' => 32.5758,
            ],
            'studio-session-45min' => [
                'venue' => 'Bombo Recording Studios',
                'venue_address' => 'Bombo Road, Nakawa, Kampala',
                'city' => 'Kampala',
                'region' => 'Central Region',
                'latitude' => 0.3443,
                'longitude' => 32.6325,
            ],
        ];

        foreach ($listings as $slug => $attributes) {
            DB::table('listings')->where('slug', $slug)->update($attributes + ['updated_at' => now()]);
        }

        $tiers = [
            'Early Bird' => 30_000,
            'Regular' => 50_000,
            'VIP Table' => 250_000,
        ];

        $eventId = DB::table('listings')->where('slug', 'neon-nights-vol-3')->value('id');

        if ($eventId !== null) {
            DB::table('listings')->where('id', $eventId)->update([
                'price_from_minor' => Money::toMinor($tiers['Early Bird']),
            ]);

            foreach ($tiers as $name => $price) {
                DB::table('ticket_types')
                    ->where('listing_id', $eventId)
                    ->where('name', $name)
                    ->update(['price_minor' => Money::toMinor($price)]);
            }
        }

        $serviceId = DB::table('listings')->where('slug', 'studio-session-45min')->value('id');

        if ($serviceId !== null) {
            DB::table('listings')->where('id', $serviceId)->update([
                'price_from_minor' => Money::toMinor(75_000),
            ]);

            DB::table('services')
                ->where('listing_id', $serviceId)
                ->update(['price_minor' => Money::toMinor(75_000)]);
        }
    }
};
