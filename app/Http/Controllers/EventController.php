<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $userId = $request->user()->id;

        $events = Event::query()
            ->with(['category', 'tags'])
            ->withCount([
                'registrations as registered_count' => function ($query) {
                    $query->where('status', 'registered');
                },
            ])
            ->withExists([
                'registrations as is_registered' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('status', 'registered');
                },
            ])
            ->where('status', 'published')
            ->where('starts_at', '>', now())
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['category_ids'] ?? []), function ($query) use ($filters) {
                $query->whereIn('category_id', $filters['category_ids']);
            })
            ->when(! empty($filters['tag_ids'] ?? []), function ($query) use ($filters) {
                $query->whereHas('tags', function ($tagQuery) use ($filters) {
                    $tagQuery->whereIn('tags.id', $filters['tag_ids']);
                });
            })
            ->orderBy('starts_at')
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('events/index', [
            'events' => $events,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category_ids' => $filters['category_ids'] ?? [],
                'tag_ids' => $filters['tag_ids'] ?? [],
            ],
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $userId = $request->user()->id;

        $event = Event::query()
            ->with(['category', 'tags'])
            ->withCount([
                'registrations as registered_count' => function ($query) {
                    $query->where('status', 'registered');
                },
            ])
            ->withExists([
                'registrations as is_registered' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('status', 'registered');
                },
            ])
            ->where('status', 'published')
            ->where('starts_at', '>', now())
            ->findOrFail($event->id);

        return Inertia::render('events/show', [
            'event' => $event,
        ]);
    }
}
