<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long an interview is, in minutes (REC-INT): the length of the calendar event. Ten minutes unless HR says otherwise.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedSmallInteger('interview_minutes')->default(10)->after('interview_place');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('interview_minutes');
        });
    }
};
