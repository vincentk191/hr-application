<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An interview is an hour unless HR says otherwise, not ten minutes. The ten that candidates already hold was only ever the
     * column's default (nobody could choose a length before this), so it becomes sixty too.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedSmallInteger('interview_minutes')->default(60)->change();
        });

        DB::table('candidates')->where('interview_minutes', 10)->update(['interview_minutes' => 60]);
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedSmallInteger('interview_minutes')->default(10)->change();
        });
    }
};
