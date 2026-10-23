<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceSlot;
use App\Models\Ticket;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Audit;
use App\Support\QrCode;
use App\Support\TicketingNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PaymentProcessor
{
    public function __construct(private readonly Inventory $inventory) {}

    /**
     * Capture a successful MoMo payment: release holds, issue tickets,
     * split the platform commission and credit the organizer wallet.
     *
     * @param  array<string,mixed>  $gatewayData
     */
    public function markPaid(Order $order, array $gatewayData, string $gateway = 'paystack'): Order
    {
        $shouldNotify = false;
        $paidOrder = DB::transaction(function () use ($order, $gatewayData, $gateway, &$shouldNotify): Order {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new RuntimeException('Order not found.');
            }

            if ($locked->status === 'paid') {
                return $locked;
            }

            $item = OrderItem::query()->where('order_id', $locked->id)->firstOrFail();

            if ($item->ticket_type_id !== null) {
                $this->inventory->confirmTicketType($item->ticket_type_id, $locked->quantity);
            }

            if ($item->service_slot_id !== null) {
                $this->inventory->confirmServiceSlot($item->service_slot_id, $locked->quantity);

                $slot = ServiceSlot::query()->find($item->service_slot_id);

                if ($slot !== null && $slot->booked_count >= $slot->capacity) {
                    $slot->forceFill(['status' => 'booked'])->save();
                }
            }

            $locked->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway' => $gateway,
                'gateway_reference' => (string) ($gatewayData['reference'] ?? $locked->tx_ref),
                'gateway_status' => 'success',
            ])->save();
            $shouldNotify = true;

            Payment::query()->updateOrCreate(
                ['order_id' => $locked->id],
                [
                    'gateway' => $gateway,
                    'gateway_reference' => (string) ($gatewayData['reference'] ?? $locked->tx_ref),
                    'currency' => $locked->currency,
                    'amount_minor' => $locked->total_minor,
                    'fee_minor' => (int) round($locked->total_minor * 0.0195),
                    'net_minor' => $locked->total_minor,
                    'status' => 'success',
                    'raw_payload' => $gatewayData,
                    'paid_at' => now(),
                ]
            );

            if ($locked->type === 'event') {
                $this->issueTickets($locked, $item);
            }

            $this->creditWallet($locked);

            Audit::log('order.paid', $locked, [
                'tx_ref' => $locked->tx_ref,
                'amount_minor' => $locked->total_minor,
            ]);

            return $locked;
        }, 3);

        if ($shouldNotify) {
            TicketingNotifier::user(
                $paidOrder->user,
                'Payment received — '.$paidOrder->tx_ref,
                'Your payment for "'.$paidOrder->listing->title.'" was confirmed. Your receipt and tickets are ready.',
                ['label' => 'Open order and download receipt', 'url' => route('buyer.orders.show', $paidOrder)],
            );
            TicketingNotifier::user(
                $paidOrder->listing->organizer?->user,
                'New paid order — '.$paidOrder->tx_ref,
                'A Mobile Money payment was confirmed for "'.$paidOrder->listing->title.'".',
                ['label' => 'Open organizer dashboard', 'url' => route('organizer.dashboard')],
            );
        }

        return $paidOrder;
    }

    /**
     * Mark a provider-declined pending order failed and release its inventory hold.
     */
    public function markFailed(Order $order, string $reason = 'Mobile Money payment was declined'): Order
    {
        $shouldNotify = false;
        $failedOrder = DB::transaction(function () use ($order, $reason, &$shouldNotify): Order {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'pending') {
                return $locked ?? $order;
            }

            $item = OrderItem::query()->where('order_id', $locked->id)->first();

            if ($item?->ticket_type_id !== null) {
                $this->inventory->releaseTicketType($item->ticket_type_id, $locked->quantity);
            }
            if ($item?->service_slot_id !== null) {
                $this->inventory->releaseServiceSlot($item->service_slot_id, $locked->quantity);
            }

            $locked->forceFill([
                'status' => 'failed',
                'gateway_status' => 'failed',
                'cancelled_at' => now(),
                'notes' => trim(($locked->notes ? $locked->notes.' | ' : '').$reason),
            ])->save();
            $shouldNotify = true;

            Payment::query()->updateOrCreate(
                ['order_id' => $locked->id],
                [
                    'gateway' => $locked->gateway,
                    'gateway_reference' => $locked->gateway_reference,
                    'currency' => $locked->currency,
                    'amount_minor' => $locked->total_minor,
                    'fee_minor' => 0,
                    'net_minor' => 0,
                    'status' => 'failed',
                    'raw_payload' => ['reason' => $reason],
                    'paid_at' => null,
                ]
            );

            return $locked;
        }, 3);

        if ($shouldNotify) {
            TicketingNotifier::user(
                $failedOrder->user,
                'Mobile Money payment failed — '.$failedOrder->tx_ref,
                'The payment for "'.$failedOrder->listing->title.'" was not completed. No ticket was issued and you were not charged by Novara.',
                ['label' => 'View order', 'url' => route('buyer.orders.show', $failedOrder)],
            );
        }

        return $failedOrder;
    }

    private function issueTickets(Order $order, OrderItem $item): void
    {
        for ($i = 0; $i < $order->quantity; $i++) {
            $seed = $order->tx_ref.'|'.$item->id.'|'.Str::random(32);

            Ticket::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'listing_id' => $order->listing_id,
                'code' => 'TKT-'.strtoupper(Str::random(8)),
                'qr_code_hash' => QrCode::hash($seed),
                'holder_name' => $order->buyer_name,
                'holder_email' => $order->buyer_email,
                'holder_phone' => $order->buyer_phone,
                'status' => 'issued',
            ]);
        }
    }

    private function creditWallet(Order $order): void
    {
        $organizer = $order->listing->organizer;

        if ($organizer === null) {
            return;
        }

        $wallet = Wallet::query()->firstOrCreate(
            ['organizer_id' => $organizer->id],
            [
                'currency' => $order->currency,
                'balance_minor' => 0,
                'pending_minor' => 0,
                'lifetime_earned_minor' => 0,
                'lifetime_refunded_minor' => 0,
            ],
        );

        if ($wallet->currency !== $order->currency) {
            $wallet->forceFill(['currency' => $order->currency])->save();
        }

        $share = $order->subtotal_minor - $order->commission_minor;

        $wallet->increment('balance_minor', $share);
        $wallet->increment('lifetime_earned_minor', $share);

        $balance = (int) $wallet->fresh()->balance_minor;

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'organizer_id' => $organizer->id,
            'order_id' => $order->id,
            'type' => 'sale',
            'direction' => 'credit',
            'amount_minor' => $share,
            'balance_after' => $balance,
            'memo' => 'Sale '.$order->tx_ref,
            'created_by' => null,
        ]);

        WalletTransaction::query()->create([
            'wallet_id' => null,
            'organizer_id' => null,
            'order_id' => $order->id,
            'type' => 'fee',
            'direction' => 'credit',
            'amount_minor' => $order->commission_minor,
            'balance_after' => null,
            'memo' => 'Platform commission '.$order->commission_bps_applied.'bps',
            'created_by' => null,
        ]);
    }
}
