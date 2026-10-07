<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A leave request (REQ-WIZ / REQ-DSH / REQ-DLG) — the first real consumer of the approval
     * workflow engine.
     *
     * No stage column: the stage and the approval chain live on the workflow instance, so this
     * table holds only what the request itself is about.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type');
            $table->date('start_on');
            $table->date('end_on');
            // Whole days only (context.md §10: no part-day leave). Stored rather than derived
            // so a later change to the working-day rules cannot silently rewrite history.
            $table->unsignedSmallInteger('days');
            $table->text('reason')->nullable();
            // Who filed it — HR may file on behalf of an employee (REQ-DSH-005).
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'start_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
