<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organizer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'business_name', 'slug', 'payout_momo_number', 'payout_provider',
        'payout_name', 'commission_bps', 'status', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_bps' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function effectiveCommissionBps(): int
    {
        return $this->commission_bps ?? (int) setting('platform_commission_bps', 2000);
    }
}
