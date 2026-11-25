<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_seats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('row_number');
            $table->unsignedInteger('seat_number');

            $table->string('status', 20)->default('available');

            $table->foreignId('reservation_id')
                ->nullable()
                ->constrained('reservations')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['event_id', 'row_number', 'seat_number'],
                'event_seats_position_unique'
            );

            $table->index(['event_id', 'status']);
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_seats');
    }
};
