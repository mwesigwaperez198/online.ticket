<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['listing_id', 'name', 'description', 'duration_minutes', 'price_minor', 'capacity_per_slot'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_minor' => 'integer',
            'capacity_per_slot' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ServiceSlot::class);
    }
}
