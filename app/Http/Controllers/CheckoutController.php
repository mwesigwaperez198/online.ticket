<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentProcessor;
use App\Support\Money;
use App\Support\TicketingNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkout, MomoGateway $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'ticket_type_id' => ['nullable', 'integer', 'exists:ticket_types,id'],
            'service_slot_id' => ['nullable', 'integer', 'exists:service_slots,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_name' => ['required', 'string', 'max:160'],
            'buyer_email' => ['required', 'email', 'max:190'],
            'buyer_phone' => ['required', 'string', 'max:30', 'regex:'.config('ticketing.momo.provider_regex', '/^(\+?256|0)\d{9}$/')],
            'momo_provider' => ['required', Rule::in(array_keys(config('ticketing.momo.providers', ['mtn' => 'MTN', 'airtel' => 'Airtel'])))],
        ], [
            'momo_provider.in' => 'That Mobile Money provider is not collecting right now. Pay with '.implode(' or ', config('ticketing.momo.providers')).'.',
        ]);

        try {
            $order = $checkout->createPendingOrder($validated);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('checkout failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'We could not reserve those tickets. Please try again.');
        }

        try {
            $initialization = $gateway->initialize([
                'tx_ref' => $order->tx_ref,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'email' => $order->buyer_email,
                'name' => $order->buyer_name,
                'phone' => $order->buyer_phone,
                'callback_url' => route('checkout.return'),
                'channels' => (array) config('ticketing.paystack.channels', ['mobile_money']),
                'metadata' => [
                    'order_id' => $order->id,
                    'tx_ref' => $order->tx_ref,
                    'listing_id' => $order->listing_id,
                    'momo_provider' => $order->momo_provider,
                ],
            ]);

            $order->forceFill([
                'gateway' => $gateway->name(),
                'gateway_reference' => $initialization['reference'],
            ])->save();

            return redirect()->away($initialization['authorization_url']);
        } catch (\Throwable $e) {
            Log::error('payment initialization failed', ['tx_ref' => $order->tx_ref, 'error' => $e->getMessage()]);

            $checkout->releaseHold($order);
            TicketingNotifier::user(
                $order->user,
                'Mobile Money checkout could not start — '.$order->tx_ref,
                'We could not start the payment request for your order. The ticket hold was released; no payment was completed.',
                ['label' => 'View order', 'url' => route('buyer.orders.show', $order)],
            );

            return back()->with('error', 'We could not start the Mobile Money checkout. Please try again.');
        }
    }

    public function return(Request $request, MomoGateway $gateway, PaymentProcessor $processor): RedirectResponse
    {
        $txRef = $request->string('reference')->toString()
            ?: $request->string('tracking_id')->toString()
            ?: $request->string('merchant_reference')->toString()
            ?: $request->string('order_tracking_id')->toString()
            ?: $request->string('tx_ref')->toString()
            ?: $request->string('trxref')->toString();

        if ($txRef === '') {
            Log::warning('payment return missing reference', [
                'query' => $request->query->all(),
                'payload' => $request->request->all(),
            ]);

            return redirect()->route('home')->with('error', 'Missing payment reference.');
        }

        $order = Order::query()->where('tx_ref', $txRef)->first();

        if ($order === null) {
            $order = Order::query()->where('gateway_reference', $txRef)->first();
        }

        if ($order === null) {
            return redirect()->route('home')->with('error', 'We could not find that order.');
        }

        if ($order->isPaid()) {
            return redirect()->route('buyer.orders.show', $order);
        }

        $reference = (string) ($order->gateway_reference ?: $txRef);
        $data = $gateway->verify($reference);

        if ((! is_array($data) || ($data['status'] ?? null) !== 'success') && $reference !== $txRef) {
            $data = $gateway->verify($txRef);
        }

        if (is_array($data) && ($data['status'] ?? null) === 'success') {
            if (! Money::matchesAmount((int) ($data['amount'] ?? 0), $order->total_minor, $order->currency)) {
                return redirect()->route('buyer.orders.show', $order)
                    ->with('error', 'The confirmed amount does not match your order.');
            }

            $processor->markPaid($order, $data, $gateway->name());

            return redirect()->route('buyer.orders.show', $order)
                ->

