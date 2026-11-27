<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventSeat;
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

        $userId = $request->user()?->id;
        $canFavorite = $userId !== null;

        $eventsQuery = Event::query()
            ->with(['categories', 'tags'])
            ->withCount([
                'registrations as registered_count' => function ($query) {
                    $query->where('status', 'registered');
                },
                'seats as unavailable_seats_count' => function ($query) {
                    $query->whereIn('status', [
                        EventSeat::STATUS_RESERVED,
                        EventSeat::STATUS_SOLD,
                    ]);
                },
            ])
            ->when($userId !== null, function ($query) use ($userId) {
                $query->withExists([
                    'registrations as is_registered' => function ($registrationQuery) use ($userId) {
                        $registrationQuery->where('user_id', $userId)
                            ->where('status', 'registered');
                    },
                ]);
            })
            ->when($canFavorite, function ($query) use ($userId) {
                $query->withExists([
                    'favoritedBy as is_favorite' => function ($favoriteQuery) use ($userId) {
                        $favoriteQuery->where('user_id', $userId);
                    },
                ]);
            })
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
                $query->whereHas('categories', function ($categoryQuery) use ($filters) {
                    $categoryQuery->whereIn('categories.id', $filters['category_ids']);
                });
            })
            ->when(! empty($filters['tag_ids'] ?? []), function ($query) use ($filters) {
                $query->whereHas('tags', function ($tagQuery) use ($filters) {
                    $tagQuery->whereIn('tags.id', $filters['tag_ids']);
                });
            })
            ->orderBy('starts_at');

        $events = $eventsQuery
            ->paginate(9)
            ->withQueryString();

        $events->getCollection()->transform(function (Event $event): Event {
            $unavailableSeatsCount = (int) $event->getAttribute('unavailable_seats_count');

            $event->setAttribute(
                'available_seat_count',
                max(0, $event->capacity - $unavailableSeatsCount)
            );

            return $event;
        });

        return Inertia::render('events/index', [
            'events' => $events,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'can_register' => $userId !== null,
            'can_favorite' => $canFavorite,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category_ids' => $filters['category_ids'] ?? [],
                'tag_ids' => $filters['tag_ids'] ?? [],
            ],
        ]);
    }

    public function archive(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $events = Event::query()
            ->with(['categories', 'tags'])
            ->withCount([
                'registrations as registered_count' => function ($query) {
                    $query->where('status', 'registered');
                },
            ])
            ->where('status', 'expired')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('starts_at')
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('events/archive', [
            'events' => $events,
            'filters' => [
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $userId = $request->user()?->id;
        $canFavorite = $userId !== null;

        $isArchived = $event->status === 'expired';

        abort_unless(
            $isArchived || (
                $event->status === 'published'
                && $event->starts_at > now()
            ),
            404
        );

        $event->load(['categories', 'tags', 'seatPriceGroups']);

        $event->loadCount([
            'registrations as registered_count' => function ($query) {
                $query->where('status', 'registered');
            },
        ]);

        if ($userId !== null && ! $isArchived) {
            $event->loadExists([
                'registrations as is_registered' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('status', 'registered');
                },
            ]);
        }

        if ($canFavorite && ! $isArchived) {
            $event->loadExists([
                'favoritedBy as is_favorite' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
            ]);
        }

        $unavailableSeats = $event->seats()
            ->whereIn('status', [
                EventSeat::STATUS_RESERVED,
                EventSeat::STATUS_SOLD,
            ])
            ->get(['row_number', 'seat_number', 'status']);

        $event->setAttribute(
            'available_seat_count',
            max(0, $event->capacity - $unavailableSeats->count())
        );

        $event->setAttribute('unavailable_seats', $unavailableSeats);

        return Inertia::render('events/show', [
            'event' => $event,
            'can_register' => $userId !== null && ! $isArchived,
            'can_favorite' => $canFavorite,
            'is_archived' => $isArchived,
        ]);
    }
}
