<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

        unset($validated['categories'], $validated['tags']);

        $event = Event::create($validated);

        $event->categories()->sync($categoryIds);
        $event->tags()->sync($tagIds);

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

        $event->load(['categories', 'tags']);

        return Inertia::render('admin/events/edit', [
            'event' => $event,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validateEvent($request);

        if ($request->hasFile('image')) {
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }

            $validated['image'] = $request->file('image')->store('events', 'public');
        } else {
            unset($validated['image']);
        }

        $categoryIds = $validated['categories'];
        $tagIds = $validated['tags'] ?? [];

        unset($validated['categories'], $validated['tags']);

        $event->update($validated);

        $event->categories()->sync($categoryIds);
        $event->tags()->sync($tagIds);

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Događaj je uspješno ažuriran.');
    }

    public function duplicate(Event $event): RedirectResponse
    {
        $event->load(['categories', 'tags']);

        $imagePath = null;

        if ($event->image && Storage::disk('public')->exists($event->image)) {
            $extension = pathinfo($event->image, PATHINFO_EXTENSION);
            $imagePath = 'events/'.uniqid('event_copy_', true).'.'.$extension;

            Storage::disk('public')->copy($event->image, $imagePath);
        }

        $copy = Event::create([
            'title' => $event->title.' (kopija)',
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'capacity' => $event->capacity,
            'seat_rows' => $event->seat_rows,
            'seat_columns' => $event->seat_columns,
            'image' => $imagePath,
            'status' => 'draft',
        ]);

        $copy->categories()->sync($event->categories->modelKeys());
        $copy->tags()->sync($event->tags->modelKeys());

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
            }
        });

        return $validator->validate();
    }
}
