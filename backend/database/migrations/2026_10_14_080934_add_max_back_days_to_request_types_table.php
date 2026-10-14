<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many calendar days back a request of this kind may start ("Boleh mundur", REQ-WIZ-014).
     * Blank means none: a request cannot start before today. HR Admin and Management are not held
     * to it. Nothing is set here: HR decides which kinds (cuti sakit, say) may be filed afterwards.
     */
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_back_days')->nullable()->after('max_days');
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('max_back_days');
        });
    }
};
