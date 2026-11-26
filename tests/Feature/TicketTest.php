<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function createTicket(
        User $user,
        string $reservationStatus = Reservation::STATUS_CONFIRMED,
        ?string $paymentStatus = null,
        bool $past = false,
    ): ReservationSeat {
        $category = Category::firstOrCreate([
            'name' => 'Test kategorija',
        ]);

        $event = Event::create([
            'title' => 'Test događaj',
            'description' => 'Opis testnog događaja',
            'location' => 'Zenica',
            'starts_at' => $past
                ? now()->subDays(5)
                : now()->addDays(5),
            'ends_at' => $past
                ? now()->subDays(4)
                : now()->addDays(6),
            'capacity' => 10,
            'seat_rows' => 2,
            'seat_columns' => 5,
            'max_tickets_per_user' => 4,
            'status' => 'published',
        ]);

        $event->categories()->attach($category);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => $reservationStatus,
            'total_amount' => $paymentStatus === null ? 0 : 20,
            'currency' => 'bam',
            'expires_at' => now()->addMinutes(32),
            'confirmed_at' => $reservationStatus === Reservation::STATUS_CONFIRMED
                ? now()
                : null,
        ]);

        if ($paymentStatus !== null) {
            Payment::create([
                'reservation_id' => $reservation->id,
                'provider' => 'stripe',
                'provider_session_id' => 'cs_test_'.$reservation->id,
                'amount' => 20,
                'currency' => 'bam',
                'status' => $paymentStatus,
            ]);
        }

        $seat = EventSeat::create([
            'event_id' => $event->id,
            'row_number' => 1,
            'seat_number' => 1,
            'status' => EventSeat::STATUS_SOLD,
            'reservation_id' => $reservation->id,
        ]);

        return ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'event_seat_id' => $seat->id,
            'price' => 20,
        ]);
    }

    public function test_korisnik_vidi_svoje_potvrdjene_besplatne_i_placene_karte(): void
    {
        $user = User::factory()->create();

        $this->createTicket($user);

        $this->createTicket(
            $user,
            paymentStatus: Payment::STATUS_PAID,
        );

        $response = $this->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tickets/index')
                ->has('upcomingTickets', 2)
                ->has('pastTickets', 0)
            );
    }

    public function test_korisnik_ne_vidi_tudje_karte(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $this->createTicket($anotherUser);

        $response = $this->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tickets/index')
                ->has('upcomingTickets', 0)
                ->has('pastTickets', 0)
            );
    }

    public function test_nepotvrdjene_i_refundirane_rezervacije_ne_prikazuju_karte(): void
    {
        $user = User::factory()->create();

        $this->createTicket(
            $user,
            reservationStatus: Reservation::STATUS_PENDING,
            paymentStatus: Payment::STATUS_PENDING,
        );

        $this->createTicket(
            $user,
            paymentStatus: Payment::STATUS_REFUNDED,
        );

        $this->createTicket(
            $user,
            reservationStatus: Reservation::STATUS_CANCELLED,
        );

        $this->createTicket(
            $user,
            reservationStatus: Reservation::STATUS_EXPIRED,
        );

        $response = $this->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tickets/index')
                ->has('upcomingTickets', 0)
                ->has('pastTickets', 0)
            );
    }

    public function test_prosle_karte_se_prikazuju_u_arhivi(): void
    {
        $user = User::factory()->create();

        $this->createTicket($user, past: true);

        $response = $this->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tickets/index')
                ->has('upcomingTickets', 0)
                ->has('pastTickets', 1)
            );
    }

    public function test_korisnik_moze_preuzeti_svoju_pdf_kartu_sa_qr_kodom(): void
    {
        $user = User::factory()->create();

        $ticket = $this->createTicket($user);

        $response = $this->actingAs($user)
            ->get(route('tickets.download', $ticket->ticket_uuid));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent(),
        );

        $this->assertGreaterThan(
            1000,
            strlen($response->getContent()),
        );
    }

    public function test_korisnik_ne_moze_preuzeti_tudju_pdf_kartu(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();

        $ticket = $this->createTicket($owner);

        $response = $this->actingAs($anotherUser)
            ->get(route('tickets.download', $ticket->ticket_uuid));

        $response->assertNotFound();
    }
}
