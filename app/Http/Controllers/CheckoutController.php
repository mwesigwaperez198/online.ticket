<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentProcessor;
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
            'buyer_phone' => ['required', 'string', 'max:30', 'regex:/^(\+?233|0)\d{9}$/'],
            'momo_provider' => ['required', Rule::in(['mtn', 'airtel', 'telecel'])],
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

            $order->forceFill(['status' => 'failed', 'cancelled_at' => now()])->save();

            return back()->with('error', 'We could not start the Mobile Money checkout. Please try again.');
        }
    }

    public function return(Request $request, MomoGateway $gateway, PaymentProcessor $processor): RedirectResponse
    {
        $txRef = $request->string('reference')->toString()
            ?: $request->string('trxref')->toString();

        if ($txRef === '') {
            return redirect()->route('home')->with('error', 'Missing payment reference.');
        }

        $order = Order::query()->where('tx_ref', $txRef)->first();

        if ($order === null) {
            return redirect()->route('home')->with('error', 'We could not find that order.');
        }

        if ($order->isPaid()) {
            return redirect()->route('buyer.orders.show', $order);
        }

        $data = $gateway->verify($txRef);

        if (is_array($data) && ($data['status'] ?? null) === 'success') {
            if ((int) ($data['amount'] ?? 0) !== $order->total_minor) {
                return redirect()->route('buyer.orders.show', $order)
                    ->with('error', 'The confirmed amount does not match your order.');
            }

            $processor->markPaid($order, $data, $gateway->name());

            return redirect()->route('buyer.orders.show', $order)
                ->with('status', 'Payment confirmed. Your tickets are ready.');
        }

        return redirect()->route('buyer.orders.show', $order)
            ->with('error', 'Payment is still pending. Refresh in a moment once your Mobile Money confirmation lands.');
    }

    public function demo(string $txRef, MomoGateway $gateway): View
    {
        $order = Order::query()->where('tx_ref', $txRef)->firstOrFail();

        return view('checkout.demo', [
            'order' => $order,
            'demo' => $gateway instanceof DemoMomoGateway,
        ]);
    }

    public function demoApprove(Request $request, string $tx_ref, MomoGateway $gateway): RedirectResponse
    {
        $order = Order::query()->where('tx_ref', $tx_ref)->first();

        if ($order === null) {
            return redirect()->route('home')->with('error', 'We could not find that order.');
        }

        if ($gateway instanceof DemoMomoGateway) {
            $gateway->approve($tx_ref);
        }

        return redirect()->route('checkout.return', ['reference' => $tx_ref]);
    }
}
