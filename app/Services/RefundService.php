<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Ticket;
use App\Models\WalletTransaction;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

final class RefundService
{
    public function __construct(private readonly MomoGateway $gateway) {}

    /**
     * Refund a whole number of tickets from an order, reversing the
     * commission split proportionally.
     */
    public function refundOrder(Order $order, array $ticketIds, string $reason, ?int $actorId = null): Refund
    {
        return DB::transaction(function () use ($order, $ticketIds, $reason, $actorId): Refund {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'paid' && $locked->status !== 'partially_refunded') {
                throw new \RuntimeException('Only paid orders can be refunded.');
            }

            $tickets = Ticket::query()
                ->where('order_id', $locked->id)
                ->whereIn('id', $ticketIds)
                ->where('status', 'issued')
                ->get();

            if ($tickets->isEmpty()) {
                throw new \RuntimeException('No refundable tickets were selected.');
            }

            $item = OrderItem::query()->where('order_id', $locked->id)->firstOrFail();
            $unit = (int) $item->unit_price_minor;
            $amount = $unit * $tickets->count();

            $refund = Refund::query()->create([
                'order_id' => $locked->id,
                'payment_id' => $locked->payment()->value('id'),
                'amount_minor' => $amount,
                'reason' => $reason,
                'status' => 'processing',
                'requested_by' => $actorId ?? auth()->id(),
                'processed_by' => $actorId ?? auth()->id(),
            ]);

            foreach ($tickets as $ticket) {
                RefundItem::query()->create([
                    'refund_id' => $refund->id,
                    'ticket_id' => $ticket->id,
                    'order_item_id' => $item->id,
                    'amount_minor' => $unit,
                ]);

                $ticket->forceFill(['status' => 'refunded', 'voided_at' => now()])->save();
            }

            $commissionReversal = (int) round($amount * ($locked->commission_bps_applied / 10_000));
            $organizerReversal = $amount - $commissionReversal;

            $organizer = $locked->listing->organizer;

            if ($organizer !== null) {
                $wallet = $organizer->wallet()->first();

                if ($wallet !== null) {
                    $wallet->decrement('balance_minor', $organizerReversal);
                    $wallet->increment('lifetime_refunded_minor', $organizerReversal);

                    WalletTransaction::query()->create([
                        'wallet_id' => $wallet->id,
                        'organizer_id' => $organizer->id,
                        'order_id' => $locked->id,
                        'type' => 'refund',
                        'direction' => 'debit',
                        'amount_minor' => -$organizerReversal,
                        'balance_after' => (int) $wallet->fresh()->balance_minor,
                        'memo' => 'Refund '.$locked->tx_ref,
                        'created_by' => $actorId ?? auth()->id(),
                    ]);
                }
            }

            WalletTransaction::query()->create([
                'wallet_id' => null,
                'organizer_id' => null,
                'order_id' => $locked->id,
                'type' => 'fee',
                'direction' => 'debit',
                'amount_minor' => -$commissionReversal,
                'balance_after' => null,
                'memo' => 'Commission reversal on refund '.$locked->tx_ref,
                'created_by' => $actorId ?? auth()->id(),
            ]);

            $remaining = Ticket::query()->where('order_id', $locked->id)->where('status', 'issued')->count();

            $locked->forceFill([
                'status' => $remaining > 0 ? 'partially_refunded' : 'refunded',
            ])->save();

            $gatewayRef = $locked->gateway_reference;

            if ($gatewayRef !== null) {
                $refund->forceFill([
                    'status' => $this->gateway->refund($gatewayRef, $amount, $reason) ? 'completed' : 'failed',
                    'gateway_ref' => $gatewayRef,
                    'completed_at' => now(),
                ])->save();
            } else {
                $refund->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }

            Audit::log('order.refunded', $locked, [
                'amount_minor' => $amount,
                'tickets' => $tickets->pluck('id')->all(),
            ]);

            return $refund->fresh();
        }, 3);
    }
}
