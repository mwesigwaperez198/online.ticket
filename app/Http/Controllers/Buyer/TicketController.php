<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $ownedOrderIds = Order::query()
            ->where('user_id', $user->id)
            ->select('id');

        $tickets = Ticket::query()
            ->where(function (Builder $query) use ($user, $ownedOrderIds): void {
                $query->whereIn('order_id', $ownedOrderIds)
                    ->orWhere('holder_email', $user->email);
            })
            ->with(['listing', 'order'])
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('buyer.tickets', ['tickets' => $tickets]);
    }
}
