<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status', 30)->default('pending');

            $table->decimal('total_amount', 10, 2);

            $table->string('currency', 3)->default('bam');

            $table->timestamp('expires_at');

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'event_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
