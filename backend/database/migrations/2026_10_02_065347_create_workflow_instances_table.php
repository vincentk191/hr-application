<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per thing moving through a workflow: a leave request, a requisition, an
     * offboarding case (CORE-WFL-001).
     *
     * The stage lives here rather than on the subject so the engine owns transitions, and so
     * every module gets time-in-stage and the approval chain without its own columns.
     */
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('definition');
            $table->string('current_stage');
            $table->timestamp('stage_entered_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // A subject runs through one workflow at a time.
            $table->unique(['subject_type', 'subject_id']);

            // Dashboards list the open instances of one definition for a company.
            $table->index(['company_id', 'definition', 'current_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
