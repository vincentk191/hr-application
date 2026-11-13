<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Google Calendar event behind a candidate's interview (REC-INT), so a new time or a cancellation changes the same
     * event, and a change made on Google itself can be noticed. The etag lets the routine check ask "has it changed?" cheaply.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('invitation_emailed');
            $table->string('google_etag')->nullable()->after('google_event_id');
            $table->string('google_meet_link', 500)->nullable()->after('google_etag');
            // Each invited person's answer on Google, by email: needsAction, accepted, declined or tentative.
            $table->json('google_responses')->nullable()->after('google_meet_link');
            // Set once when the event is found deleted on Google; HR decides what to do (inviting again makes a new one).
            $table->timestamp('google_missing_at')->nullable()->after('google_responses');
            $table->timestamp('google_synced_at')->nullable()->after('google_missing_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['google_event_id', 'google_etag', 'google_meet_link', 'google_responses', 'google_missing_at', 'google_synced_at']);
        });
    }
};
