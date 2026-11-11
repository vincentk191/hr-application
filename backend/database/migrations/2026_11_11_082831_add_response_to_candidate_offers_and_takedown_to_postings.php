<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The candidate's answer to a sent offer (REC-OFS-004/008): when, and why they said no. And whether the listing HR put up
     * on a platform has been taken down again once the position is filled (REC-FIL-005).
     */
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('emailed_at');
            $table->text('decline_reason')->nullable()->after('responded_at');
        });

        Schema::table('requisition_postings', function (Blueprint $table) {
            $table->timestamp('taken_down_at')->nullable()->after('posted_on');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_postings', function (Blueprint $table) {
            $table->dropColumn('taken_down_at');
        });

        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->dropColumn(['responded_at', 'decline_reason']);
        });
    }
};
