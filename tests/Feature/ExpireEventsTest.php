<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireEventsTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Test kategorija',
        ]);
    }

    private function createEvent(array $attributes): Event
    {
        $event = Event::create(array_merge([
            'title' => 'Test događaj',
            'description' => 'Opis testnog događaja',
            'location' => 'Zenica',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'capacity' => 50,
            'status' => 'published',
        ], $attributes));

        $event->categories()->attach($this->createCategory());

        return $event;
    }

    public function test_zavrseni_objavljeni_dogadaj_postaje_expired(): void
    {
        $event = $this->createEvent([]);

        $this->artisan('events:expire')
            ->expectsOutput('Broj završenih događaja: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'expired',
        ]);
    }

    public function test_dogadaj_koji_jos_traje_ostaje_published(): void
    {
        $event = $this->createEvent([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->artisan('events:expire')
            ->expectsOutput('Broj završenih događaja: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
        ]);
    }

    public function test_draft_dogadaj_ne_postaje_expired(): void
    {
        $event = $this->createEvent([
            'status' => 'draft',
        ]);

        $this->artisan('events:expire')
            ->expectsOutput('Broj završenih događaja: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'draft',
        ]);
    }
}
