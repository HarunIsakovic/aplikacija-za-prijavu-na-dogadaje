<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TicketController extends Controller
{
    /**
     * Display the authenticated user's tickets.
     */
    public function index(Request $request): Response
    {
        $tickets = $this->availableTickets()
            ->with([
                'reservation.event.categories',
                'eventSeat',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->whereHas('reservation.event', function ($eventQuery) use ($search): void {
                    $eventQuery->where('title', 'like', "%{$search}%");
                });
            })
            ->get()
            ->sortByDesc(fn (ReservationSeat $ticket) => $ticket->reservation->event->starts_at)
            ->values();

        $now = now();

        return Inertia::render('tickets/index', [
            'upcomingTickets' => $tickets
                ->filter(fn (ReservationSeat $ticket) => $ticket->reservation->event->starts_at >= $now)
                ->values()
                ->map(fn (ReservationSeat $ticket) => $this->formatTicket($ticket)),
            'pastTickets' => $tickets
                ->filter(fn (ReservationSeat $ticket) => $ticket->reservation->event->starts_at < $now)
                ->values()
                ->map(fn (ReservationSeat $ticket) => $this->formatTicket($ticket)),
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Download a PDF ticket with its QR code.
     */
    public function download(Request $request, string $ticketUuid): SymfonyResponse
    {
        $ticket = $this->availableTickets()
            ->with([
                'reservation.event.categories',
                'eventSeat',
            ])
            ->where('ticket_uuid', $ticketUuid)
            ->firstOrFail();

        $qrCode = new QrCode(
            data: $ticket->ticket_uuid,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        );

        $writer = new PngWriter;
        $qrResult = $writer->write($qrCode);

        $ticketData = $this->formatTicket($ticket);

        return Pdf::loadView('pdf.ticket', [
            'ticket' => $ticketData,
            'qrCode' => 'data:image/png;base64,'.base64_encode($qrResult->getString()),
        ])
            ->setPaper('a4')
            ->download('karta-'.$ticket->ticket_uuid.'.pdf');
    }

    /** @return Builder<ReservationSeat> */
    private function availableTickets()
    {
        return ReservationSeat::query()
            ->whereHas('reservation', function ($query): void {
                $query
                    ->where('user_id', auth()->id())
                    ->where('status', Reservation::STATUS_CONFIRMED)
                    ->where(function ($paymentQuery): void {
                        $paymentQuery
                            ->whereDoesntHave('payments')
                            ->orWhereHas('payments', function ($payment): void {
                                $payment->where('status', Payment::STATUS_PAID);
                            });
                    });
            });
    }

    /**
     * Format ticket data for the frontend and PDF.
     *
     * @return array<string, mixed>
     */
    private function formatTicket(ReservationSeat $ticket): array
    {
        $reservation = $ticket->reservation;
        $event = $reservation->event;
        $seat = $ticket->eventSeat;

        return [
            'ticket_uuid' => $ticket->ticket_uuid,
            'event_title' => $event->title,
            'event_location' => $event->location,
            'event_starts_at' => $event->starts_at->format('d.m.Y. H:i'),
            'event_ends_at' => $event->ends_at?->format('d.m.Y. H:i'),
            'categories' => $event->categories->pluck('name')->values(),
            'seat' => $seat
                ? 'Red '.$seat->row_number.', sjedište '.$seat->seat_number
                : 'Nije dodijeljeno',
            'price' => number_format((float) $ticket->price, 2, ',', '.').' '.$reservation->currency,
        ];
    }
}
