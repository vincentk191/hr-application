<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A holiday or event that repeats (ATT-WIZ-002) is stored as one ordinary row per occurrence, so
     * everything that reads the calendar keeps working unchanged. The rows made together share a
     * `series_id`, which is how "delete the whole series" finds them, and `repeat` says how they
     * repeat, for display.
     */
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->after('ends_on')->index();
            $table->string('repeat', 10)->nullable()->after('series_id');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn(['series_id', 'repeat']);
        });
    }
};
