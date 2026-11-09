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
        $filters = $request->only(['category_id', 'tag_id']);

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
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->when($request->filled('tag_id'), function ($query) use ($request) {
                $query->whereHas('tags', function ($tagQuery) use ($request) {
                    $tagQuery->where('tags.id', $request->tag_id);
                });
            })
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('events/index', [
            'events' => $events,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => $filters,
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
