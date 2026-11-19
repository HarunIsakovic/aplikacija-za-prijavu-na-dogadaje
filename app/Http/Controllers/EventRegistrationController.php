<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventRegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $registrations = $request->user()
            ->eventRegistrations()
            ->with(['event.categories'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('event', function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('registrations/index', [
            'registrations' => $registrations,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($event->id);

            if ($event->status !== 'published') {
                throw ValidationException::withMessages([
                    'registration' => 'Ovaj događaj nije objavljen.',
                ]);
            }

            if (now()->greaterThanOrEqualTo($event->starts_at)) {
                throw ValidationException::withMessages([
                    'registration' => 'Prijavljivanje na događaj više nije moguće jer je počeo.',
                ]);
            }

            $registration = $event->registrations()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($registration && $registration->status === 'registered') {
                throw ValidationException::withMessages([
                    'registration' => 'Već ste prijavljeni na ovaj događaj.',
                ]);
            }

            $registeredCount = $event->registrations()
                ->where('status', 'registered')
                ->count();

            if ($registeredCount >= $event->capacity) {
                throw ValidationException::withMessages([
                    'registration' => 'Događaj je popunjen.',
                ]);
            }

            if ($registration) {
                $registration->update([
                    'status' => 'registered',
                    'attended' => false,
                ]);
            } else {
                $event->registrations()->create([
                    'user_id' => $request->user()->id,
                    'status' => 'registered',
                    'attended' => false,
                ]);
            }
        });

        return back()->with('success', 'Uspješno ste se prijavili na događaj.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($event->id);

            $registration = $event->registrations()
                ->where('user_id', $request->user()->id)
                ->where('status', 'registered')
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                throw ValidationException::withMessages([
                    'registration' => 'Nemate aktivnu prijavu za ovaj događaj.',
                ]);
            }

            $registration->update([
                'status' => 'cancelled',
            ]);
        });

        return back()->with('success', 'Uspješno ste otkazali prijavu.');
    }
}
