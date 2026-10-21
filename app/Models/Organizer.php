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
        'payout_name', 'instagram', 'whatsapp', 'facebook', 'website',
        'commission_bps', 'status', 'approved_at', 'notes',
        'payout_submitted_at', 'payout_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_bps' => 'integer',
            'approved_at' => 'datetime',
            'payout_submitted_at' => 'datetime',
            'payout_approved_at' => 'datetime',
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

    public function hasPayoutDetails(): bool
    {
        return filled($this->payout_momo_number) && filled($this->payout_provider);
    }

    public function hasSocialLinks(): bool
    {
        return filled($this->instagram)
            || filled($this->whatsapp)
            || filled($this->facebook)
            || filled($this->website);
    }

    public function isAwaitingPayoutApproval(): bool
    {
        return $this->hasPayoutDetails()
            && $this->payout_approved_at === null;
    }

    /**
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = array_filter([
            'Instagram' => $this->instagram,
            'WhatsApp' => $this->whatsapp,
            'Facebook' => $this->facebook,
            'Website' => $this->website,
        ]);

        return array_map(static fn (string $link): string => trim($link), $links);
    }
}
