<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingImage extends Model
{
    protected $fillable = ['listing_id', 'path', 'sort_order'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
