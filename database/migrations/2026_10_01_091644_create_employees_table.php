<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The employee dossier record (EMP-DIR, EMP-ADD).
     *
     * `user_id` is nullable because warehouse staff exist as data-only records with no login
     * (CORE-RBA-006, EMP-DIR-008). `manager_id` carries the reporting line (CORE-ORG-003).
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_number');
            $table->string('full_name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_status')->default('active');
            $table->string('employment_type')->nullable();
            $table->date('joined_on')->nullable();
            $table->date('exited_on')->nullable();
            $table->timestamps();

            // An employee number identifies a person within their own company, not group-wide.
            $table->unique(['company_id', 'employee_number']);

            // The directory lists one company's active staff by department (EMP-DIR-001/002).
            $table->index(['company_id', 'employment_status']);
            $table->index(['company_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
