<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id', 'type', 'slug', 'title', 'description', 'venue', 'venue_address',
        'city', 'region', 'latitude', 'longitude',
        'cover_image', 'video_url', 'starts_at', 'ends_at', 'price_from_minor', 'status',
        'featured', 'allow_refunds', 'refund_window_hours', 'max_per_order', 'meta', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'allow_refunds' => 'boolean',
            'featured' => 'boolean',
            'meta' => 'array',
            'price_from_minor' => 'integer',
            'max_per_order' => 'integer',
            'refund_window_hours' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $listing): void {
            if (blank($listing->slug)) {
                $listing->slug = static::uniqueSlug($listing->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'live']);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('organizer', fn (Builder $q) => $q->where('user_id', $userId));
    }

    /**
     * Listings hand-picked for the curated "Our Picks" row on the home page.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'live'], true);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The public URL of the event banner, if one was uploaded.
     */
    public function coverImageUrl(): ?string
    {
        if (! is_string($this->cover_image) || $this->cover_image === '') {
            return null;
        }

        if (str_starts_with($this->cover_image, 'http://') || str_starts_with($this->cover_image, 'https://')) {
            return $this->cover_image;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->cover_image);
    }

    /**
     * A hardened embed URL for an organizer-supplied trailer, or null.
     *
     * The raw URL is never trusted as an iframe source. The host is parsed and
     * must exactly match an allow list of known video platforms, and only the
     * video identifier is carried across, so a crafted URL can never introduce
     * a new host, path or query into the embed.
     *
     * @return array{url: string, host: string}|null
     */
    public function videoEmbed(): ?array
    {
        if (! is_string($this->video_url) || $this->video_url === '') {
            return null;
        }

        $parts = parse_url($this->video_url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID.
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            if ($host === 'youtu.be') {
                $id = $path;
            } elseif (is_string($query['v'] ?? null)) {
                $id = $query['v'];
            } else {
                $id = explode('/', $path)[1] ?? '';
            }

            if ($this->validVideoId($id)) {
                return ['url' => 'https://www.youtube-nocookie.com/embed/'.$id, 'host' => 'YouTube'];
            }

            return null;
        }

        // Vimeo: vimeo.com/123456789
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('/^\d+$/', $path) === 1) {
            return ['url' => 'https://player.vimeo.com/video/'.$path, 'host' => 'Vimeo'];
        }

        return null;
    }

    /**
     * A video identifier is a short, conservative token: no traversal, no
     * markup, no separators.
     */
    private function validVideoId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1;
    }
}
