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
        return DB::transaction(function () use ($order, $gatewayData, $gateway): Order {
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
            'created_by' => auth()->id(),
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
            'created_by' => auth()->id(),
        ]);
    }
}
