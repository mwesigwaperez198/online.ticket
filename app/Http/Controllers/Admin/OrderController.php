<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
        $status = $request->string('status')->toString();

        $orders = Order::query()
            ->with(['listing.organizer', 'user'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders', ['orders' => $orders, 'status' => $status]);
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $ticketIds = Ticket::query()
            ->where('order_id', $order->id)
            ->where('status', 'issued')
            ->pluck('id')
            ->all();

        if ($ticketIds === []) {
            return back()->with('error', 'No refundable tickets on this order.');
        }

        $refunds->refundOrder($order, $ticketIds, $validated['reason'], $request->user()->id);

        return back()->with('status', 'Refund issued.');
    }
}
