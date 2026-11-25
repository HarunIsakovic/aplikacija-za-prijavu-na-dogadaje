<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $events = Event::query()
            ->with('categories')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('starts_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/events/index', [
            'events' => $events,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        $tags = Tag::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/events/create', [
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEvent($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('events', 'public');
        }

        $categoryIds = $validated['categories'];
        $tagIds = $validated['tags'] ?? [];
        $priceGroups = $validated['price_groups'];

        unset(
            $validated['categories'],
            $validated['tags'],
            $validated['price_groups']
        );

        DB::transaction(function () use ($validated, $categoryIds, $tagIds, $priceGroups) {
            $event = Event::create($validated);

            $event->categories()->sync($categoryIds);
            $event->tags()->sync($tagIds);
            $event->seatPriceGroups()->createMany($priceGroups);
        });

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Događaj je uspješno kreiran.');
    }

    public function edit(Event $event): Response
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        $tags = Tag::query()
            ->orderBy('name')
            ->get();

        $event->load(['categories', 'tags', 'seatPriceGroups']);

        return Inertia::render('admin/events/edit', [
            'event' => $event,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validateEvent($request);

        $categoryIds = $validated['categories'];
        $tagIds = $validated['tags'] ?? [];
        $priceGroups = $validated['price_groups'];

        unset(
            $validated['categories'],
            $validated['tags'],
            $validated['price_groups']
        );

        $oldImage = DB::transaction(function () use (
            $event,
            $request,
            $validated,
            $categoryIds,
            $tagIds,
            $priceGroups,
        ): ?string {
            // Isti redoslijed zaključavanja koristi se i prilikom rezervacije.
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasReservations = $lockedEvent->reservations()->exists();

            if ($hasReservations) {
                $lockedEvent->load('seatPriceGroups');

                $seatConfigurationChanged =
                    (int) $lockedEvent->capacity !== (int) $validated['capacity']
                    || (int) $lockedEvent->seat_rows !== (int) $validated['seat_rows']
                    || (int) $lockedEvent->seat_columns !== (int) $validated['seat_columns']
                    || (int) $lockedEvent->max_tickets_per_user !== (int) $validated['max_tickets_per_user']
                    || $this->normalizePriceGroups(
                        $lockedEvent->seatPriceGroups->toArray()
                    ) !== $this->normalizePriceGroups($priceGroups);

                if ($seatConfigurationChanged) {
                    throw ValidationException::withMessages([
                        'capacity' => 'Konfiguraciju sjedišta, kapacitet, maksimalan broj karata i cijene nije moguće mijenjati jer za ovaj događaj već postoji rezervacija.',
                    ]);
                }
            }

            $oldImage = $lockedEvent->image;

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')
                    ->store('events', 'public');
            } else {
                unset($validated['image']);
            }

            $lockedEvent->update($validated);

            $lockedEvent->categories()->sync($categoryIds);
            $lockedEvent->tags()->sync($tagIds);

            if (! $hasReservations) {
                $lockedEvent->seatPriceGroups()->delete();
                $lockedEvent->seatPriceGroups()->createMany($priceGroups);
            }

            return $oldImage;
        });

        if (
            $request->hasFile('image')
            && $oldImage
        ) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Događaj je uspješno ažuriran.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array{from_row: int, to_row: int, price_cents: int}>
     */
    private function normalizePriceGroups(array $groups): array
    {
        $normalized = array_map(
            fn (array $group): array => [
                'from_row' => (int) $group['from_row'],
                'to_row' => (int) $group['to_row'],
                'price_cents' => (int) round(
                    (float) $group['price'] * 100
                ),
            ],
            $groups,
        );

        usort(
            $normalized,
            fn (array $a, array $b): int => $a['from_row'] <=> $b['from_row']
        );

        return $normalized;
    }

    public function duplicate(Event $event): RedirectResponse
    {
        $event->load(['categories', 'tags', 'seatPriceGroups']);

        $imagePath = null;

        if ($event->image && Storage::disk('public')->exists($event->image)) {
            $extension = pathinfo($event->image, PATHINFO_EXTENSION);
            $imagePath = 'events/'.uniqid('event_copy_', true).'.'.$extension;

            Storage::disk('public')->copy($event->image, $imagePath);
        }

        $copy = DB::transaction(function () use ($event, $imagePath) {
            $copy = Event::create([
                'title' => $event->title.' (kopija)',
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'capacity' => $event->capacity,
                'seat_rows' => $event->seat_rows,
                'seat_columns' => $event->seat_columns,
                'max_tickets_per_user' => $event->max_tickets_per_user,
                'image' => $imagePath,
                'status' => 'draft',
            ]);

            $copy->categories()->sync($event->categories->modelKeys());
            $copy->tags()->sync($event->tags->modelKeys());

            $copy->seatPriceGroups()->createMany(
                $event->seatPriceGroups->map(fn ($group) => [
                    'from_row' => $group->from_row,
                    'to_row' => $group->to_row,
                    'price' => $group->price,
                ])->all()
            );

            return $copy;
        });

        return redirect()
            ->route('admin.events.edit', $copy)
            ->with('success', 'Događaj je uspješno dupliciran. Kopija je sačuvana kao nacrt.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        if ($event->image) {
            Storage::disk('public')->delete($event->image);
        }

        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Događaj je uspješno obrisan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEvent(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'seat_rows' => ['required', 'integer', 'min:1'],
            'seat_columns' => ['required', 'integer', 'min:1'],
            'max_tickets_per_user' => ['required', 'integer', 'min:1', 'lte:capacity'],
            'price_groups' => ['required', 'array', 'min:1'],
            'price_groups.*.from_row' => ['required', 'integer', 'min:1'],
            'price_groups.*.to_row' => ['required', 'integer', 'min:1'],
            'price_groups.*.price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (
                $validator->errors()->has('capacity')
                || $validator->errors()->has('seat_rows')
                || $validator->errors()->has('seat_columns')
            ) {
                return;
            }

            $capacity = (int) $request->input('capacity');
            $seatRows = (int) $request->input('seat_rows');
            $seatColumns = (int) $request->input('seat_columns');

            if ($capacity !== $seatRows * $seatColumns) {
                $validator->errors()->add(
                    'capacity',
                    'Kapacitet mora biti jednak broju redova pomnoženom brojem kolona.'
                );

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $priceGroups = $request->input('price_groups', []);
            $ranges = [];
            $hasInvalidRange = false;

            foreach ($priceGroups as $index => $group) {
                $fromRow = (int) $group['from_row'];
                $toRow = (int) $group['to_row'];

                if ($fromRow > $toRow) {
                    $validator->errors()->add(
                        "price_groups.{$index}.from_row",
                        'Početni red ne može biti veći od završnog reda.'
                    );

                    $hasInvalidRange = true;

                    continue;
                }

                if ($toRow > $seatRows) {
                    $validator->errors()->add(
                        "price_groups.{$index}.to_row",
                        'Završni red ne može biti veći od ukupnog broja redova.'
                    );

                    $hasInvalidRange = true;

                    continue;
                }

                $ranges[] = [
                    'from' => $fromRow,
                    'to' => $toRow,
                ];
            }

            if ($hasInvalidRange) {
                return;
            }

            usort($ranges, fn (array $a, array $b) => $a['from'] <=> $b['from']);

            $expectedRow = 1;

            foreach ($ranges as $range) {
                if ($range['from'] !== $expectedRow) {
                    $validator->errors()->add(
                        'price_groups',
                        'Cjenovne grupe moraju pokriti sve redove tačno jednom, bez praznina i preklapanja.'
                    );

                    return;
                }

                $expectedRow = $range['to'] + 1;
            }

            if ($expectedRow !== $seatRows + 1) {
                $validator->errors()->add(
                    'price_groups',
                    'Cjenovne grupe moraju pokriti sve redove tačno jednom, bez praznina i preklapanja.'
                );
            }
        });

        return $validator->validate();
    }
}
