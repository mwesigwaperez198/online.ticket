<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CheckInController extends Controller
{
    use OrganizerScope;

    public function index(): View
    {
        return view('organizer.scanner', [
            'listings' => $this->scopedListings()->orderBy('starts_at')->get(),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:190'],
            'listing_id' => ['nullable', 'integer'],
        ]);

        $hash = trim($validated['code']);
        $listingId = $validated['listing_id'] ?? null;

        $ticket = Ticket::query()->where('qr_code_hash', $hash)->first();

        if ($ticket === null) {
            $ticket = Ticket::query()->where('code', $hash)->first();
        }

        if ($ticket === null) {
            $this->logScan(null, null, $request, 'invalid', 'Unknown code');

            return response()->json(['result' => 'invalid', 'message' => 'This code is not recognised.']);
        }

        $organizerId = $this->organizer()?->id ?? 0;

        if ($ticket->listing->organizer_id !== $organizerId) {
            $this->logScan($ticket, null, $request, 'wrong_event', 'Ticket belongs to another organizer');

            return response()->json(['result' => 'wrong_event', 'message' => 'This ticket belongs to another event.']);
        }

        if ($listingId !== null && (int) $ticket->listing_id !== (int) $listingId) {
            $this->logScan($ticket, null, $request, 'wrong_event', 'Wrong listing selected');

            return response()->json(['result' => 'wrong_event', 'message' => 'This ticket is for a different event.']);
        }

        if ($ticket->status === 'used') {
            $this->logScan($ticket, null, $request, 'already_used', 'Already scanned');

            return response()->json([
                'result' => 'already_used',
                'message' => 'Already checked in at '.$ticket->used_at?->format('d M Y H:i'),
                'holder' => $ticket->holder_name,
            ]);
        }

        if ($ticket->status === 'refunded') {
            $this->logScan($ticket, null, $request, 'refunded', 'Refunded ticket');

            return response()->json(['result' => 'refunded', 'message' => 'This ticket was refunded.']);
        }

        if ($ticket->status === 'void') {
            $this->logScan($ticket, null, $request, 'void', 'Void ticket');

            return response()->json(['result' => 'void', 'message' => 'This ticket was voided.']);
        }

        DB::transaction(function () use ($ticket, $request): void {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'issued') {
                return;
            }

            $locked->forceFill([
                'status' => 'used',
                'used_at' => now(),
                'used_by' => $request->user()->id,
            ])->save();

            $this->logScan($locked, $locked, $request, 'valid', 'Checked in');
        });

        return response()->json([
            'result' => 'valid',
            'message' => 'Welcome in, '.$ticket->holder_name.'!',
            'holder' => $ticket->holder_name,
            'code' => $ticket->code,
        ]);
    }

    private function logScan(?Ticket $ticket, ?Ticket $scanned, Request $request, string $result, string $notes): void
    {
        TicketScan::query()->create([
            'ticket_id' => $ticket?->id ?? 0,
            'listing_id' => $ticket?->listing_id ?? 0,
            'scanned_by' => $request->user()->id,
            'result' => $result,
            'notes' => $notes,
            'scanned_at' => now(),
        ]);
    }
}
