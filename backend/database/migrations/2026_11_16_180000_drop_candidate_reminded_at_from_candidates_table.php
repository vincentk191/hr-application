<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The candidate is no longer emailed a reminder the day before the interview (the Google Calendar invitation reminds them),
 * so there is nothing left to remember about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('candidate_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->timestamp('candidate_reminded_at')->nullable()->after('invitation_emailed');
        });
    }
};
