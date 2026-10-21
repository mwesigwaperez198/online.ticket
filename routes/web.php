<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrganizerApprovalController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Buyer\TicketController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Organizer\CheckInController;
use App\Http\Controllers\Organizer\DashboardController as OrganizerDashboardController;
use App\Http\Controllers\Organizer\ListingController;
use App\Http\Controllers\Organizer\PayoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

// Public marketplace funnel.
Route::get('/', [MarketplaceController::class, 'index'])->name('home');
Route::get('/events/{slug}', [MarketplaceController::class, 'show'])->name('events.show');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/return', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/demo/{tx_ref}', [CheckoutController::class, 'demo'])->name('checkout.demo');
    Route::post('/checkout/demo/{tx_ref}', [CheckoutController::class, 'demoApprove'])->name('checkout.demo.approve');

    Route::get('/my-tickets', [TicketController::class, 'index'])->name('buyer.tickets');
    Route::get('/my-orders', [BuyerOrderController::class, 'index'])->name('buyer.orders');
    Route::get('/my-orders/{order}', [BuyerOrderController::class, 'show'])->name('buyer.orders.show');
    Route::post('/my-orders/{order}/refund', [BuyerOrderController::class, 'refund'])->name('buyer.orders.refund');

    Route::middleware('role:organizer')->prefix('organizer')->name('organizer.')->group(function (): void {
        Route::get('/', [OrganizerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
        Route::post('/listings/{listing}/publish', [ListingController::class, 'publish'])->name('listings.publish');
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');

        Route::get('/scanner', [CheckInController::class, 'index'])->name('scanner');
        Route::post('/scanner/check', [CheckInController::class, 'check'])->name('scanner.check');

        Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [CmsController::class, 'index'])->name('dashboard');
        Route::get('/approvals', [OrganizerApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{organizer}', [OrganizerApprovalController::class, 'update'])->name('approvals.update');
        Route::post('/listings/{listing}/moderate', [ModerationController::class, 'update'])->name('listings.moderate');
        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger');
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/status', [AdminUserController::class, 'update'])->name('users.status');
        Route::post('/settings/commission', [SettingsController::class, 'updateCommission'])->name('settings.commission');
        Route::post('/settings/payout/{organizer}', [SettingsController::class, 'payout'])->name('settings.payout');
    });
});
