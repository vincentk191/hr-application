<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the two references that could not be declared when their tables were created.
     *
     * `departments.supervisor_employee_id` needs the employees table. `users.company_id`
     * pins an account to one operating company; null means a parent-wide account that may
     * switch between companies or view them combined (CORE-USR-004, CORE-NAV-003/004).
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('supervisor_employee_id')
                ->nullable()
                ->after('name')
                ->constrained('employees')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_employee_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
