<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_event', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['event_id', 'category_id']);
        });

        DB::table('events')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunkById(500, function ($events) {
                $now = now();

                $categoryEvents = $events->map(fn ($event) => [
                    'event_id' => $event->id,
                    'category_id' => $event->category_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('category_event')->insert($categoryEvents);
            });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
        });

        DB::table('category_event')
            ->orderBy('event_id')
            ->get()
            ->each(function ($categoryEvent) {
                DB::table('events')
                    ->where('id', $categoryEvent->event_id)
                    ->update([
                        'category_id' => $categoryEvent->category_id,
                    ]);
            });

        Schema::dropIfExists('category_event');
    }
};
