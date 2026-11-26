<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->uuid('ticket_uuid')->nullable();
        });

        DB::table('reservation_seats')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($seats): void {
                foreach ($seats as $seat) {
                    DB::table('reservation_seats')
                        ->where('id', $seat->id)
                        ->update([
                            'ticket_uuid' => (string) Str::uuid(),
                        ]);
                }
            });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->unique('ticket_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropUnique(['ticket_uuid']);
            $table->dropColumn('ticket_uuid');
        });
    }
};
