<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\TicketingNotifier;
use Illuminate\Support\Facades\DB;

final class HoldExpiryService
{
    public function __construct(private readonly Inventory $inventory) {}

    /**
     * Release expired pending holds.
     *
     * Render free instances have no cron, so expiry runs opportunistically:
     * a single bounded batch whenever the app handles a request.
     */
    public function sweep(int $limit = 25): int
    {
        $orders = Order::query()
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        $released = 0;

        foreach ($orders as $order) {
            try {
                $done = DB::transaction(function () use ($order): bool {
                    /** @var Order|null $locked */
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                    if ($locked === null || $locked->status !== 'pending') {
                        return false;
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

                    $locked->forceFill(['status' => 'expired', 'cancelled_at' => now()])->save();

                    return true;
                }, 2);

                if ($done) {
                    $released++;
                    TicketingNotifier::user(
                        $order->user,
                        'Mobile Money order expired — '.$order->tx_ref,
                        'The payment hold for "'.$order->listing->title.'" expired before payment was confirmed. You can start a new checkout if tickets are still available.',
                        ['label' => 'Browse events', 'url' => route('home')],
                    );
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $released;
    }
}
