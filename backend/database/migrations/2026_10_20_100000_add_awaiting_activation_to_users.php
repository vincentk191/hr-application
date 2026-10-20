<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account that an employee registered for themselves waits for HR to activate it (CORE-ACC-006). Until then
     * it cannot sign in, so a wrongly claimed account never gets to see anything.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('awaiting_activation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('awaiting_activation');
        });
    }
};
