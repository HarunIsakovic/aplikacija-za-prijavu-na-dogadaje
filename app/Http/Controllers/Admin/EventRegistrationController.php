<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventRegistrationController extends Controller
{
    public function index(Request $request, Event $event): Response
    {
        $filters = $request->only(['search']);

        $registrations = $event->registrations()
            ->with('user')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at')
            ->paginate(10)
            ->withQueryString();

        $activeRegistrations = $event->registrations()
            ->where('status', 'registered')
            ->count();

        $attendedCount = $event->registrations()
            ->where('status', 'registered')
            ->where('attended', true)
            ->count();

        return Inertia::render('admin/registrations/index', [
            'event' => $event,
            'registrations' => $registrations,
            'filters' => $filters,
            'activeRegistrations' => $activeRegistrations,
            'attendedCount' => $attendedCount,
        ]);
    }

    public function update(
        Request $request,
        EventRegistration $registration
    ): RedirectResponse {
        $validated = $request->validate([
            'attended' => ['required', 'boolean'],
        ]);

        if ($registration->status !== 'registered') {
            throw ValidationException::withMessages([
                'attendance' => 'Prisustvo se može evidentirati samo za aktivne prijave.',
            ]);
        }

        $registration->update([
            'attended' => $validated['attended'],
        ]);

        return back()->with(
            'success',
            'Prisustvo je uspješno ažurirano.'
        );
    }
}
