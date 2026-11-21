<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('seat_rows')->default(1);
            $table->unsignedInteger('seat_columns')->default(1);
        });

        DB::table('events')->update([
            'seat_columns' => DB::raw('capacity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['seat_rows', 'seat_columns']);
        });
    }
};
