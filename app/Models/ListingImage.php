<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
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
        $path = $this->path;

        if (is_string($path) && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))) {
            return $path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }
}
