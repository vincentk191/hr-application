<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual corrections (EMP-ATT-001).
     *
     * The punch log stays append-only: a scan is never edited or deleted. A wrong scan is voided
     * (kept, marked, with who and why) and, if needed, a corrected one is added beside it with
     * source "manual" and a reason. Everything derived from punches ignores voided ones, so the
     * day recomputes itself (EMP-ATT-002), and the original is still there to be audited.
     */
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('source');
            $table->foreignId('created_by_user_id')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('created_by_user_id');
            $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['reason', 'voided_at', 'void_reason']);
        });
    }
};
