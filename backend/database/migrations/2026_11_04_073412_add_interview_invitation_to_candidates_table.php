<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 04, Interview (REC-INT): HR invites a shortlisted candidate with the interview's time and place, and the
     * invitation carries a personal, single-use, expiring link to the candidate form in this app.
     *
     * Only a hash of the link's token is kept, so a copy of the database cannot be turned into working links; HR
     * sees the link once, when it is made, and can make a new one. The answers the candidate gives are kept as a
     * snapshot (the checklist lines as they read when answered), so editing the requisition later does not
     * rewrite what someone agreed to.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('invite_token_hash', 64)->nullable()->unique()->after('cv_size');
            $table->timestamp('invited_at')->nullable()->after('invite_token_hash');
            $table->timestamp('invite_expires_at')->nullable()->after('invited_at');
            $table->foreignId('invited_by_user_id')->nullable()->after('invite_expires_at')->constrained('users')->nullOnDelete();
            $table->timestamp('interview_at')->nullable()->after('invited_by_user_id');
            $table->string('interview_mode', 10)->nullable()->after('interview_at');
            $table->string('interview_place', 300)->nullable()->after('interview_mode');
            $table->timestamp('form_submitted_at')->nullable()->after('interview_place');
            $table->json('form_data')->nullable()->after('form_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropColumn([
                'invite_token_hash', 'invited_at', 'invite_expires_at', 'interview_at', 'interview_mode',
                'interview_place', 'form_submitted_at', 'form_data',
            ]);
        });
    }
};
