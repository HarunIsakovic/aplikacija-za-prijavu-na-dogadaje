<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $favorites = $user->favorites()
            ->with(['categories', 'tags'])
            ->withCount([
                'registrations as registered_count' => function ($query) {
                    $query->where('status', 'registered');
                },
            ])
            ->withExists([
                'registrations as is_registered' => function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where('status', 'registered');
                },
            ])
            ->orderBy('starts_at')
            ->get()
            ->each(function (Event $event): void {
                $event->setAttribute(
                    'can_register',
                    $event->status === 'published'
                        && $event->starts_at->isFuture()
                        && $event->registered_count < $event->capacity
                );
            });

        return Inertia::render('favorites/index', [
            'favorites' => $favorites,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        abort_unless(
            $event->status === 'published' && $event->starts_at > now(),
            404
        );

        $request->user()
            ->favorites()
            ->syncWithoutDetaching([$event->id]);

        return back()->with('success', 'Događaj je dodan u omiljene.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $request->user()
            ->favorites()
            ->detach($event->id);

        return back()->with('success', 'Događaj je uklonjen iz omiljenih.');
    }
}
