<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->string('payout_momo_number', 30)->nullable();
            $table->string('payout_provider', 30)->nullable();
            $table->string('payout_name')->nullable();
            $table->unsignedInteger('commission_bps')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->string('type', 20)->default('event');
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('cover_image')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('price_from_minor')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('allow_refunds')->default(true);
            $table->unsignedInteger('refund_window_hours')->default(72);
            $table->unsignedInteger('max_per_order')->default(10);
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3)->default('GHS');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('held_count')->default(0);
            $table->unsignedInteger('max_per_order')->default(10);
            $table->dateTime('sales_start')->nullable();
            $table->dateTime('sales_end')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['listing_id', 'active']);
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedBigInteger('price_minor');
            $table->unsignedInteger('capacity_per_slot')->default(1);
            $table->timestamps();
        });

        Schema::create('service_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('booked_count')->default(0);
            $table->unsignedInteger('held_count')->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['service_id', 'starts_at']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('tx_ref', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('type', 20)->default('event');
            $table->string('status', 30)->default('pending');
            $table->string('currency', 3)->default('GHS');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedInteger('commission_bps_applied');
            $table->unsignedBigInteger('commission_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone', 30);
            $table->string('momo_provider', 30)->nullable();
            $table->string('gateway', 30)->default('paystack');
            $table->string('gateway_reference', 120)->nullable();
            $table->string('gateway_status', 40)->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->foreignId('service_slot_id')->nullable()->constrained('service_slots')->nullOnDelete();
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('code', 24)->unique();
            $table->string('qr_code_hash', 64)->unique();
            $table->string('holder_name');
            $table->string('holder_email')->nullable();
            $table->string('holder_phone', 30)->nullable();
            $table->string('seat_label')->nullable();
            $table->string('status', 20)->default('issued');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
        });

        Schema::create('ticket_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 30);
            $table->string('notes')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['ticket_id', 'scanned_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 30)->default('paystack');
            $table->string('gateway_reference', 120)->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedBigInteger('net_minor');
            $table->string('status', 30)->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway_reference']);
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->unique()->constrained('organizers')->cascadeOnDelete();
            $table->string('currency', 3)->default('GHS');
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('pending_minor')->default(0);
            $table->unsignedBigInteger('lifetime_earned_minor')->default(0);
            $table->unsignedBigInteger('lifetime_refunded_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('organizers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type', 30);
            $table->string('direction', 10);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after')->nullable();
            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organizer_id', 'type']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason')->nullable();
            $table->string('status', 30)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway_ref', 120)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('method', 20)->default('momo');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 120);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('ticket_scans');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('service_slots');
        Schema::dropIfExists('services');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('organizers');
    }
};
