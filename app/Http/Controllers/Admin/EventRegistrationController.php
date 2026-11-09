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
    public function index(Event $event): Response
    {
        $registrations = $event->registrations()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('admin/registrations/index', [
            'event' => $event,
            'registrations' => $registrations,
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
