<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_seats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_seat_id')
                ->constrained('event_seats')
                ->restrictOnDelete();

            $table->decimal('price', 10, 2);

            $table->timestamps();

            $table->unique(
                ['reservation_id', 'event_seat_id'],
                'reservation_seats_unique'
            );

            $table->index('event_seat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_seats');
    }
};
