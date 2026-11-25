<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Direktna prijava bez plaćanja više nije dozvoljena.
     * Prijava se kreira tek nakon uspješne Stripe naplate.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        throw ValidationException::withMessages([
            'registration' => 'Prijava na događaj moguća je isključivo putem rezervacije i plaćanja.',
        ]);
    }

    /**
     * Otkazivanje ćemo obraditi kroz sistem rezervacija.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        throw ValidationException::withMessages([
            'registration' => 'Otkazivanje prijave trenutno nije dostupno.',
        ]);
    }
}
