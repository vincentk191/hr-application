<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR approves a finished checklist before the case can go on to sign-off (OFF-CHK-011). The approval
     * is cleared whenever the checklist changes after it, so it always vouches for what is there now.
     */
    public function up(): void
    {
        Schema::table('offboarding_cases', function (Blueprint $table) {
            $table->timestamp('checklist_approved_at')->nullable();
            $table->foreignId('checklist_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offboarding_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_approved_by_user_id');
            $table->dropColumn('checklist_approved_at');
        });
    }
};
