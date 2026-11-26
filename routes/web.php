<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventRegistrationController as AdminEventRegistrationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Javni pregled događaja.
Route::get('/events', [EventController::class, 'index'])
    ->name('events.index');

// Arhiva završenih događaja.
Route::get('/events/archive', [EventController::class, 'archive'])
    ->name('events.archive');

// Javni detalji događaja preko slug-a.
Route::get('/events/{event:slug}', [EventController::class, 'show'])
    ->name('events.show');

// Stripe webhook. Sigurnost se provjerava Stripe potpisom.
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->name('stripe.webhook');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Omiljeni događaji.
    Route::get('/my-favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    Route::post('/events/{event}/favorite', [FavoriteController::class, 'store'])
        ->name('events.favorites.store');

    Route::delete('/events/{event}/favorite', [FavoriteController::class, 'destroy'])
        ->name('events.favorites.destroy');

    // Pregled postojećih prijava.
    Route::get('/my-registrations', [EventRegistrationController::class, 'index'])
        ->name('registrations.index');

    // Rezervacije i Stripe Checkout.
    Route::post('/events/{event}/reservations', [ReservationController::class, 'store'])
        ->name('reservations.store');

    Route::get('/reservations/{reservation}/success', [ReservationController::class, 'success'])
        ->name('reservations.success');

    Route::get('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
        ->name('reservations.cancel');

    Route::get('/my-tickets', [TicketController::class, 'index'])
        ->name('tickets.index');

    Route::get('/tickets/{ticketUuid}/pdf', [TicketController::class, 'download'])
        ->name('tickets.download');
});

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('users.update');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('categories.index');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->name('categories.store');

        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->name('categories.update');

        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->name('categories.destroy');

        Route::get('/events', [AdminEventController::class, 'index'])
            ->name('events.index');

        Route::get('/events/create', [AdminEventController::class, 'create'])
            ->name('events.create');

        Route::post('/events', [AdminEventController::class, 'store'])
            ->name('events.store');

        Route::post('/events/{event}/duplicate', [AdminEventController::class, 'duplicate'])
            ->name('events.duplicate');

        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])
            ->name('events.edit');

        Route::put('/events/{event}', [AdminEventController::class, 'update'])
            ->name('events.update');

        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])
            ->name('events.destroy');

        Route::get('/tags', [TagController::class, 'index'])
            ->name('tags.index');

        Route::post('/tags', [TagController::class, 'store'])
            ->name('tags.store');

        Route::put('/tags/{tag}', [TagController::class, 'update'])
            ->name('tags.update');

        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])
            ->name('tags.destroy');

        Route::get('/events/{event}/registrations', [AdminEventRegistrationController::class, 'index'])
            ->name('events.registrations.index');

        Route::put('/registrations/{registration}/attendance', [AdminEventRegistrationController::class, 'update'])
            ->name('registrations.attendance.update');
    });

require __DIR__.'/settings.php';
