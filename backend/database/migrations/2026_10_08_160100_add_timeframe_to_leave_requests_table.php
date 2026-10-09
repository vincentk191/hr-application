<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional time of day for a request ("09:00" to "11:00"), for the ones that are about hours
     * rather than whole days. It is stored as "HH:MM" text, and only describes the request: the days
     * are still counted from the dates.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('start_time', 5)->nullable()->after('end_on');
            $table->string('end_time', 5)->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
