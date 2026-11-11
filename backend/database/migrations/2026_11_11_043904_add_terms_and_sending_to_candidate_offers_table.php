<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The terms HR fills in once Management has set the salary (start date, probation), and the moment the offer was
     * marked as sent with whether the candidate was also emailed (REC-OFR).
     */
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->date('start_on')->nullable()->after('salary');
            $table->unsignedTinyInteger('probation_months')->nullable()->after('start_on');
            $table->timestamp('sent_at')->nullable()->after('proposed_at');
            $table->foreignId('sent_by_user_id')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable()->after('sent_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_user_id');
            $table->dropColumn(['start_on', 'probation_months', 'sent_at', 'emailed_at']);
        });
    }
};
