<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What was sent to the candidate (REC-INT), kept so HR can read it again next to the schedule. The personal link in
     * it is replaced by a marker before it is stored: the link is the credential, and only its hash may be kept.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('invitation_subject', 300)->nullable()->after('interview_place');
            $table->text('invitation_message')->nullable()->after('invitation_subject');
            $table->boolean('invitation_emailed')->default(false)->after('invitation_message');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['invitation_subject', 'invitation_message', 'invitation_emailed']);
        });
    }
};
