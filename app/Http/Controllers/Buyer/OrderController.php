<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('listing')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('buyer.orders', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): View|RedirectResponse
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $order->load(['listing.organizer', 'items.ticketType', 'items.serviceSlot.service', 'tickets', 'payment']);

        return view('buyer.order', ['order' => $order]);
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $listing = $order->listing;

        $withinWindow = $listing->allow_refunds
            && $order->paid_at !== null
            && $order->paid_at->gt(now()->subHours((int) $listing->refund_window_hours));

        if (! $withinWindow) {
            return back()->with('error', 'The refund window for this event has closed. Contact support for help.');
        }

        $ticketIds = (array) $request->input('ticket_ids', []);

        $refundable = Ticket::query()
            ->where('order_id', $order->id)
            ->where('status', 'issued')
            ->whereIn('id', $ticketIds)
            ->count();

        if ($refundable === 0) {
            return back()->with('error', 'Select at least one ticket to refund.');
        }

        $refunds->refundOrder(
            $order,
            Ticket::query()->where('order_id', $order->id)->where('status', 'issued')->whereIn('id', $ticketIds)->pluck('id')->all(),
            (string) $request->input('reason', 'Buyer requested refund'),
            $request->user()->id,
        );

        return back()->with('status', 'Refund processed for '.$refundable.' ticket(s).');
    }
}
