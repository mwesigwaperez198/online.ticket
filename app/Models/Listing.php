<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id', 'type', 'slug', 'title', 'description', 'venue', 'city', 'region',
        'cover_image', 'starts_at', 'ends_at', 'price_from_minor', 'status', 'allow_refunds',
        'refund_window_hours', 'max_per_order', 'meta', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'allow_refunds' => 'boolean',
            'meta' => 'array',
            'price_from_minor' => 'integer',
            'max_per_order' => 'integer',
            'refund_window_hours' => 'integer',
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
}
