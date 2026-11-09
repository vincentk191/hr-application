<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two small facts about an interview (REC-INT). The note HR typed into the invitation is kept, so the mail-merge
     * file carries the same words the candidate was sent. And when HR and the direct superior were told that the
     * interview is waiting for an evaluation is kept, so it is said once; a new time clears it, like the other reminders.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->text('invitation_note')->nullable()->after('invitation_message');
            $table->timestamp('evaluation_prompted_at')->nullable()->after('interviewers_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['invitation_note', 'evaluation_prompted_at']);
        });
    }
};
