<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id', 'currency', 'balance_minor', 'pending_minor',
        'lifetime_earned_minor', 'lifetime_refunded_minor',
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'pending_minor' => 'integer',
            'lifetime_earned_minor' => 'integer',
            'lifetime_refunded_minor' => 'integer',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
