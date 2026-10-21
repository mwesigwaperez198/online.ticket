<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

final class Inventory
{
    /**
     * Atomically reserve ticket inventory.
     *
     * The conditional UPDATE is the concurrency guard: it is atomic on SQLite,
     * MySQL and PostgreSQL, unlike a SELECT followed by an UPDATE.
     */
    public function holdTicketType(int $ticketTypeId, int $qty): bool
    {
        if ($qty < 1) {
            return false;
        }

        return DB::update(
            'UPDATE ticket_types SET held_count = held_count + ? WHERE id = ? AND capacity - sold_count - held_count >= ?',
            [$qty, $ticketTypeId, $qty]
        ) === 1;
    }

    public function releaseTicketType(int $ticketTypeId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        DB::update(
            'UPDATE ticket_types SET held_count = held_count - ? WHERE id = ? AND held_count >= ?',
            [$qty, $ticketTypeId, $qty]
        );
    }

    public function confirmTicketType(int $ticketTypeId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        DB::update(
            'UPDATE ticket_types SET held_count = held_count - ?, sold_count = sold_count + ? WHERE id = ? AND held_count >= ?',
            [$qty, $qty, $ticketTypeId, $qty]
        );
    }

    public function holdServiceSlot(int $slotId, int $qty): bool
    {
        if ($qty < 1) {
            return false;
        }

        return DB::update(
            'UPDATE service_slots SET held_count = held_count + ? WHERE id = ? AND capacity - booked_count - held_count >= ?',
            [$qty, $slotId, $qty]
        ) === 1;
    }

    public function releaseServiceSlot(int $slotId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        DB::update(
            'UPDATE service_slots SET held_count = held_count - ? WHERE id = ? AND held_count >= ?',
            [$qty, $slotId, $qty]
        );
    }

    public function confirmServiceSlot(int $slotId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        DB::update(
            'UPDATE service_slots SET held_count = held_count - ?, booked_count = booked_count + ? WHERE id = ? AND held_count >= ?',
            [$qty, $qty, $slotId, $qty]
        );
    }

    public function remainingFor(TicketType $ticketType): int
    {
        return max(0, $ticketType->capacity - $ticketType->sold_count - $ticketType->held_count);
    }
}
