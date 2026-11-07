<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interview reminders (REC-INT): the day before the candidate is emailed, and on the day HR and the direct superior
     * get a notice. Each is sent once; a new interview time (a re-invite) clears them so the new day is reminded too.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->timestamp('candidate_reminded_at')->nullable()->after('invitation_emailed');
            $table->timestamp('interviewers_reminded_at')->nullable()->after('candidate_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['candidate_reminded_at', 'interviewers_reminded_at']);
        });
    }
};
