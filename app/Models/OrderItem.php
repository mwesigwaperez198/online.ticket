<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'ticket_type_id', 'service_slot_id', 'unit_price_minor', 'quantity', 'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function serviceSlot(): BelongsTo
    {
        return $this->belongsTo(ServiceSlot::class);
    }

    public function label(): string
    {
        return $this->ticketType?->name ?? $this->serviceSlot?->service?->name ?? 'Ticket';
    }
}
