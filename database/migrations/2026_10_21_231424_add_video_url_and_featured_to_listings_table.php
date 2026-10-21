<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organizer media and curation.
     *
     * video_url lets an organizer attach a trailer (YouTube or Vimeo) to their
     * listing; it is stored as the raw URL and only rendered as an embed when
     * the model can prove the host is on the allow list.
     *
     * featured drives the curated "Our Picks" row on the marketplace home so
     * the platform team can hand-pick what greets a visitor.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->string('video_url', 500)->nullable()->after('cover_image');
            $table->boolean('featured')->default(false)->after('status');
            $table->index(['featured', 'status'], 'listings_featured_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_featured_status_index');
            $table->dropColumn(['video_url', 'featured']);
        });
    }
};
