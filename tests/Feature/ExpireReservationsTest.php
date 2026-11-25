<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventSeat;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationCooldown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $event = Event::create([
            'title' => 'Test događaj',
            'description' => 'Opis testnog događaja',
            'location' => 'Zenica',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
            'capacity' => 10,
            'seat_rows' => 2,
            'seat_columns' => 5,
            'max_tickets_per_user' => 4,
            'status' => 'published',
        ]);

        $category = Category::create([
            'name' => 'Test kategorija',
        ]);

        $event->categories()->attach($category);

        return $event;
    }

    private function createPendingReservation(
        Event $event,
        User $user,
    ): array {
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => Reservation::STATUS_PENDING,
            'total_amount' => 20,
            'currency' => 'bam',
            'expires_at' => now()->subMinute(),
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'amount' => 20,
            'currency' => 'bam',
            'status' => Payment::STATUS_PENDING,
        ]);

        $seat = EventSeat::create([
            'event_id' => $event->id,
            'row_number' => 1,
            'seat_number' => 1,
            'status' => EventSeat::STATUS_RESERVED,
            'reservation_id' => $reservation->id,
        ]);

        return [$reservation, $payment, $seat];
    }

    public function test_istekla_rezervacija_oslobadja_sjediste_i_kreira_pauzu(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();

        [$reservation, $payment, $seat] = $this->createPendingReservation(
            $event,
            $user,
        );

        $this->artisan('reservations:expire')
            ->expectsOutput('Broj isteklih rezervacija: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $seat->id,
            'status' => EventSeat::STATUS_AVAILABLE,
            'reservation_id' => null,
        ]);

        $cooldown = ReservationCooldown::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNotNull($cooldown);
        $this->assertEquals(
            $reservation->id,
            $cooldown->reservation_id,
        );
        $this->assertTrue(
            $cooldown->expires_at->between(
                now()->addMinutes(14),
                now()->addMinutes(16),
            ),
        );
    }

    public function test_neistekla_rezervacija_ostaje_na_cekanju(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();

        [$reservation, $payment, $seat] = $this->createPendingReservation(
            $event,
            $user,
        );

        $reservation->update([
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('reservations:expire')
            ->expectsOutput('Broj isteklih rezervacija: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $seat->id,
            'status' => EventSeat::STATUS_RESERVED,
            'reservation_id' => $reservation->id,
        ]);
    }

    public function test_vec_placena_rezervacija_ne_istice(): void
    {
        $event = $this->createEvent();
        $user = User::factory()->create();

        [$reservation, $payment, $seat] = $this->createPendingReservation(
            $event,
            $user,
        );

        $payment->update([
            'status' => Payment::STATUS_PAID,
        ]);

        $this->artisan('reservations:expire')
            ->expectsOutput('Broj isteklih rezervacija: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('event_seats', [
            'id' => $seat->id,
            'status' => EventSeat::STATUS_RESERVED,
            'reservation_id' => $reservation->id,
        ]);

        $this->assertDatabaseMissing('reservation_cooldowns', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }
}
