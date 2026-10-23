<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'order_item_id', 'listing_id', 'code', 'qr_code_hash', 'holder_name',
        'holder_email', 'holder_phone', 'seat_label', 'status', 'used_at', 'voided_at', 'used_by',
    ];

    protected $hidden = ['qr_code_hash'];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(TicketScan::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }
}
