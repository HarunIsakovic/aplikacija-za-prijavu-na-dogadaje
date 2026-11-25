<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminEventReservationTestData(): array
{
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $category = Category::create([
        'name' => 'Radionica',
    ]);

    $event = Event::create([
        'title' => 'Laravel radionica',
        'description' => 'Opis radionice.',
        'location' => 'Zenica',
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
        'capacity' => 30,
        'seat_rows' => 5,
        'seat_columns' => 6,
        'max_tickets_per_user' => 4,
        'image' => null,
        'status' => 'published',
    ]);

    $event->categories()->attach($category);

    $event->seatPriceGroups()->create([
        'from_row' => 1,
        'to_row' => 5,
        'price' => 20,
    ]);

    return [
        'admin' => $admin,
        'user' => $user,
        'category' => $category,
        'event' => $event,
    ];
}

function adminEventReservationTestPayload(
    Event $event,
    Category $category,
): array {
    return [
        'categories' => [$category->id],
        'title' => $event->title,
        'description' => $event->description,
        'location' => $event->location,
        'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
        'ends_at' => null,
        'capacity' => $event->capacity,
        'seat_rows' => $event->seat_rows,
        'seat_columns' => $event->seat_columns,
        'max_tickets_per_user' => $event->max_tickets_per_user,
        'price_groups' => [
            [
                'from_row' => 1,
                'to_row' => 5,
                'price' => 20,
            ],
        ],
        'status' => $event->status,
        'tags' => [],
    ];
}

function assertAdminEventSeatConfigurationIsLocked(array $data, string $status): void
{
    $reservation = Reservation::create([
        'user_id' => $data['user']->id,
        'event_id' => $data['event']->id,
        'status' => $status,
        'total_amount' => 40,
        'currency' => 'bam',
        'expires_at' => now()->addMinutes(32),
        'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? now() : null,
        'expired_at' => $status === Reservation::STATUS_EXPIRED ? now() : null,
    ]);

    $payload = adminEventReservationTestPayload(
        $data['event'],
        $data['category'],
    );

    $payload['capacity'] = 36;
    $payload['seat_rows'] = 6;
    $payload['price_groups'] = [
        [
            'from_row' => 1,
            'to_row' => 6,
            'price' => 20,
        ],
    ];

    test()->actingAs($data['admin'])
        ->put(
            route('admin.events.update', $data['event']),
            $payload,
        )
        ->assertSessionHasErrors('capacity');

    test()->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => $status,
    ]);

    test()->assertDatabaseHas('events', [
        'id' => $data['event']->id,
        'capacity' => 30,
        'seat_rows' => 5,
    ]);
}

test('administrator ne moze promijeniti konfiguraciju sjedista nakon rezervacije', function () {
    $data = createAdminEventReservationTestData();

    Reservation::create([
        'user_id' => $data['user']->id,
        'event_id' => $data['event']->id,
        'status' => Reservation::STATUS_PENDING,
        'total_amount' => 40,
        'currency' => 'bam',
        'expires_at' => now()->addMinutes(32),
    ]);

    $payload = adminEventReservationTestPayload(
        $data['event'],
        $data['category'],
    );

    $payload['capacity'] = 36;
    $payload['seat_rows'] = 6;
    $payload['price_groups'] = [
        [
            'from_row' => 1,
            'to_row' => 6,
            'price' => 20,
        ],
    ];

    $response = $this->actingAs($data['admin'])
        ->put(
            route('admin.events.update', $data['event']),
            $payload,
        );

    $response->assertSessionHasErrors('capacity');

    $this->assertDatabaseHas('events', [
        'id' => $data['event']->id,
        'capacity' => 30,
        'seat_rows' => 5,
    ]);
});

test('administrator moze promijeniti obicne podatke dogadjaja nakon rezervacije', function () {
    $data = createAdminEventReservationTestData();

    Reservation::create([
        'user_id' => $data['user']->id,
        'event_id' => $data['event']->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'total_amount' => 40,
        'currency' => 'bam',
        'expires_at' => now()->addMinutes(32),
        'confirmed_at' => now(),
    ]);

    $payload = adminEventReservationTestPayload(
        $data['event'],
        $data['category'],
    );

    $payload['title'] = 'Izmijenjena Laravel radionica';
    $payload['description'] = 'Novi opis radionice.';
    $payload['location'] = 'Sarajevo';

    $response = $this->actingAs($data['admin'])
        ->put(
            route('admin.events.update', $data['event']),
            $payload,
        );

    $response->assertRedirect(route('admin.events.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('events', [
        'id' => $data['event']->id,
        'title' => 'Izmijenjena Laravel radionica',
        'description' => 'Novi opis radionice.',
        'location' => 'Sarajevo',
        'capacity' => 30,
        'seat_rows' => 5,
    ]);
});

test('administrator moze promijeniti konfiguraciju sjedista kada rezervacije ne postoje', function () {
    $data = createAdminEventReservationTestData();

    $payload = adminEventReservationTestPayload(
        $data['event'],
        $data['category'],
    );

    $payload['capacity'] = 36;
    $payload['seat_rows'] = 6;
    $payload['seat_columns'] = 6;
    $payload['max_tickets_per_user'] = 5;
    $payload['price_groups'] = [
        [
            'from_row' => 1,
            'to_row' => 3,
            'price' => 20,
        ],
        [
            'from_row' => 4,
            'to_row' => 6,
            'price' => 30,
        ],
    ];

    $response = $this->actingAs($data['admin'])
        ->put(
            route('admin.events.update', $data['event']),
            $payload,
        );

    $response->assertRedirect(route('admin.events.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('events', [
        'id' => $data['event']->id,
        'capacity' => 36,
        'seat_rows' => 6,
        'seat_columns' => 6,
        'max_tickets_per_user' => 5,
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $data['event']->id,
        'from_row' => 1,
        'to_row' => 3,
        'price' => 20,
    ]);

    $this->assertDatabaseHas('event_seat_price_groups', [
        'event_id' => $data['event']->id,
        'from_row' => 4,
        'to_row' => 6,
        'price' => 30,
    ]);
});

test('administrator ne moze promijeniti konfiguraciju nakon otkazane rezervacije', function () {
    $data = createAdminEventReservationTestData();

    assertAdminEventSeatConfigurationIsLocked(
        $data,
        Reservation::STATUS_CANCELLED,
    );
});

test('administrator ne moze promijeniti konfiguraciju nakon istekle rezervacije', function () {
    $data = createAdminEventReservationTestData();

    assertAdminEventSeatConfigurationIsLocked(
        $data,
        Reservation::STATUS_EXPIRED,
    );
});
