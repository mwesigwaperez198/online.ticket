<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the production read paths.
 *
 * The marketplace is queried by organizer on every dashboard load, by listing
 * for the public event pages and by transaction reference when a payment
 * callback lands. Without these keys the platform degrades into table scans
 * as soon as the ticket table grows, which is exactly where a sales spike
 * hurts most.
 *
 * The "event" of the platform is a listing, so the listing id is the
 * equivalent of an event id here: it is indexed on orders, tickets and
 * listings so an event page and an organizer ledger stay fast.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const INDEXES = [
        'organizers' => [
            ['user_id'],
        ],
        'listings' => [
            ['organizer_id'],
            ['organizer_id', 'status'],
        ],
        'orders' => [
            ['user_id'],
            ['listing_id', 'status'],
            ['gateway_reference'],
        ],
        'order_items' => [
            ['order_id'],
        ],
        'tickets' => [
            ['order_id'],
            ['holder_email'],
        ],
        'payments' => [
            ['order_id'],
        ],
        'wallet_transactions' => [
            ['order_id'],
            ['wallet_id'],
        ],
        'refunds' => [
            ['order_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
