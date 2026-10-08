<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account state beyond "has a password" (CORE-USR-001/002/005, CORE-AUT-003/004).
     *
     * is_active: an account is deactivated, never deleted, so its history stays attributable.
     * must_change_password: set when an administrator creates an account or resets a password, so a
     * temporary password cannot become a permanent one. last_login_at: shown on the account list.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'must_change_password', 'last_login_at']);
        });
    }
};
