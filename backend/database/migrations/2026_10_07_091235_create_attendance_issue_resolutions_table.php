<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issues the attendance screen has been told to leave alone (ATT-ISS-004, ATT-ISS-006).
     *
     * Issues themselves are not stored: they are detected from the punches each time, so fixing
     * the cause (a correction, a leave request) makes an issue disappear on its own. What does
     * need remembering is a human decision that an issue is acceptable — excused, with a reason
     * and who said so — which is this table. One row per employee, day and kind of issue.
     */
    public function up(): void
    {
        Schema::create('attendance_issue_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->string('resolution', 20);
            $table->string('reason');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date', 'type']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_issue_resolutions');
    }
};
