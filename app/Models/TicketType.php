<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id', 'name', 'description', 'price_minor', 'currency', 'capacity',
        'max_per_order', 'sales_start', 'sales_end', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'capacity' => 'integer',
            'sold_count' => 'integer',
            'held_count' => 'integer',
            'max_per_order' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'sales_start' => 'datetime',
            'sales_end' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->sold_count - $this->held_count);
    }

    public function isSoldOut(): bool
    {
        return $this->remaining() <= 0;
    }

    public function isOnSale(): bool
    {
        $now = now();

        if (! $this->active) {
            return false;
        }

        if ($this->sales_start !== null && $this->sales_start->isFuture()) {
            return false;
        }

        if ($this->sales_end !== null && $this->sales_end->isPast()) {
            return false;
        }

        return true;
    }
}
