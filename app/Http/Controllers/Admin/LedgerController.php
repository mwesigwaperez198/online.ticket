<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();

        $transactions = WalletTransaction::query()
            ->with(['organizer', 'order'])
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        return view('admin.ledger', [
            'transactions' => $transactions,
            'type' => $type,
            'totals' => [
                'credits' => (int) WalletTransaction::query()->where('direction', 'credit')->sum('amount_minor'),
                'debits' => (int) WalletTransaction::query()->where('direction', 'debit')->sum('amount_minor'),
                'platform_fees' => (int) WalletTransaction::query()->where('type', 'fee')->sum('amount_minor'),
                'gross' => (int) Order::query()->where('status', 'paid')->sum('total_minor'),
            ],
        ]);
    }
}
