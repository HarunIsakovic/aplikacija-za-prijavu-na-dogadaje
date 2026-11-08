<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;


class EventController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->with('category')
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('admin/events/index', [
            'events' => $events,
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
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('events', 'public');
        }

        $tagIds = $validated['tags'] ?? [];

        unset($validated['tags']);

        $event = Event::create($validated);

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

        $event->load('tags');

        return Inertia::render('admin/events/edit', [
            'event' => $event,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);

        if ($request->hasFile('image')) {
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }

            $validated['image'] = $request->file('image')->store('events', 'public');
        } else {
            unset($validated['image']);
        }

        $tagIds = $validated['tags'] ?? [];

        unset($validated['tags']);

        $event->update($validated);

        $event->tags()->sync($tagIds);

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Događaj je uspješno ažuriran.');
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
}
