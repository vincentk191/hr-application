<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account no longer needs an email (CORE-REG-004): staff who register themselves may give
     * none, and sign in with their employee ID. The column stays unique, so two accounts still
     * cannot share one; several accounts may have none.
     *
     * NIK is half of the key an employee registers with, so it is unique across the group
     * (EMP-ADD-002). Records that have none are not affected: a unique index allows any number of
     * nulls. If two records already share a NIK this fails, and HR has to correct one first.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
