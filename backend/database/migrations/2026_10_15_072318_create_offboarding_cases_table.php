<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An offboarding case (OFB-xxxx): one employee leaving the company (OFF-WIZ).
     *
     * What the case is about lives here; its stage, approval and comments live on the workflow
     * instance, as for every other module. Everything but the employee is nullable because a draft
     * can be saved half-filled and finished later (OFF-WIZ-002); submitting checks it is complete.
     */
    public function up(): void
    {
        Schema::create('offboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exit_type', 20)->nullable();
            $table->date('notice_on')->nullable();
            $table->date('last_working_on')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('compensation_months')->default(0);
            $table->text('compensation_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_cases');
    }
};
