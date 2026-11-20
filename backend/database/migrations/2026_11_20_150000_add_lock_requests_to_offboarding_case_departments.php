<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locking a department's checklist is HR's: the owner asks for it (lock_requested_at) and, once it is locked, asks for it to be
 * opened again (unlock_requested_at). Neither is set while nothing is being asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offboarding_case_departments', function (Blueprint $table) {
            $table->timestamp('lock_requested_at')->nullable()->after('confirmed_by_user_id');
            $table->timestamp('unlock_requested_at')->nullable()->after('lock_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('offboarding_case_departments', function (Blueprint $table) {
            $table->dropColumn(['lock_requested_at', 'unlock_requested_at']);
        });
    }
};
