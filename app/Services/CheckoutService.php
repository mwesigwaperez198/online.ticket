<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceSlot;
use App\Models\TicketType;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CheckoutService
{
    public function __construct(private readonly Inventory $inventory) {}

    /**
     * @param  array{listing_id:int, ticket_type_id?:int|null, service_slot_id?:int|null, quantity:int, buyer_name:string, buyer_email:string, buyer_phone:string, momo_provider?:string|null}  $input
     */
    public function createPendingOrder(array $input): Order
    {
        return DB::transaction(function () use ($input): Order {
            $listing = Listing::query()
                ->whereKey($input['listing_id'])
                ->whereIn('status', ['published', 'live'])
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = max(1, min((int) $listing->max_per_order, (int) $input['quantity']));

            [$unitPrice, $ticketTypeId, $serviceSlotId] = $listing->isService()
                ? $this->holdService($listing, $input, $quantity)
                : $this->holdEvent($listing, $input, $quantity);

            $subtotal = $unitPrice * $quantity;
            $bps = $listing->organizer?->effectiveCommissionBps() ?? Money::commissionBps();
            $split = Money::split($subtotal, $bps);

            $order = Order::query()->create([
                'tx_ref' => self::generateTxRef(),
                'user_id' => auth()->id(),
                'listing_id' => $listing->id,
                'type' => $listing->type,
                'status' => 'pending',
                'currency' => Money::currency(),
                'quantity' => $quantity,
                'subtotal_minor' => $subtotal,
                'commission_bps_applied' => $bps,
                'commission_minor' => $split['commission_minor'],
                'total_minor' => $subtotal,
                'buyer_name' => $input['buyer_name'],
                'buyer_email' => $input['buyer_email'],
                'buyer_phone' => $input['buyer_phone'],
                'momo_provider' => $input['momo_provider'] ?? null,
                'gateway' => 'paystack',
                'expires_at' => now()->addMinutes((int) setting('hold_minutes', 10)),
            ]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'ticket_type_id' => $ticketTypeId,
                'service_slot_id' => $serviceSlotId,
                'unit_price_minor' => $unitPrice,
                'quantity' => $quantity,
                'line_total_minor' => $subtotal,
            ]);

            return $order;
        }, 3);
    }

    /**
     * Release the inventory held by an order that never reached the gateway.
     *
     * A checkout that cannot even start the Mobile Money request must not
     * keep a seat reserved: the hold would only be released when the order
     * expires, and every failed attempt would eat into the live capacity.
     */
    public function releaseHold(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'pending') {
                return;
            }

            $item = OrderItem::query()->where('order_id', $locked->id)->first();

            if ($item !== null) {
                if ($item->ticket_type_id !== null) {
                    $this->inventory->releaseTicketType($item->ticket_type_id, $locked->quantity);
                }

                if ($item->service_slot_id !== null) {
                    $this->inventory->releaseServiceSlot($item->service_slot_id, $locked->quantity);
                }
            }

            $locked->forceFill(['status' => 'failed', 'cancelled_at' => now()])->save();
        }, 2);
    }

    public static function generateTxRef(): string
    {
        do {
            $ref = 'TK'.strtoupper(Str::random(14));
        } while (Order::query()->where('tx_ref', $ref)->exists());

        return $ref;
    }

    /**
     * @return array{0:int,1:int|null,2:int|null}
     */
    private function holdEvent(Listing $listing, array $input, int $quantity): array
    {
        $type = TicketType::query()
            ->where('listing_id', $listing->id)
            ->when($input['ticket_type_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->where('active', true)
            ->where(function ($q): void {
                $q->whereNull('sales_start')->orWhere('sales_start', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('sales_end')->orWhere('sales_end', '>=', now());
            })
            ->orderBy('sort_order')
            ->lockForUpdate()
            ->firstOrFail();

        if (! $this->inventory->holdTicketType($type->id, $quantity)) {
            throw new RuntimeException('Not enough tickets left for '.$type->name.'.');
        }

        return [$type->price_minor, $type->id, null];
    }

    /**
     * @return array{0:int,1:null,2:int}
     */
    private function holdService(Listing $listing, array $input, int $quantity): array
    {
        $slot = ServiceSlot::query()
            ->where('service_id', $listing->services()->value('id'))
            ->when($input['service_slot_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->lockForUpdate()
            ->firstOrFail();

        if (! $this->inventory->holdServiceSlot($slot->id, $quantity)) {
            throw new RuntimeException('That slot is fully booked.');
        }

        return [(int) $slot->service()->value('price_minor'), null, $slot->id];
    }
}
