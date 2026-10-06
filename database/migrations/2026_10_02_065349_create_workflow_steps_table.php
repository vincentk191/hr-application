<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ordered approval chain for an instance (CORE-WFL-002).
     *
     * A step knows the role it needs and, once it becomes the current step, the person that
     * role resolved to. Keeping both is what lets offboarding say "the employee's direct
     * supervisor" (OFF-WZ1-002) while still naming one accountable person, and it means a role
     * change mid-case does not silently hand the decision to someone else.
     *
     * Company-tagged on purpose: "approvals waiting on me" is queried straight off this table
     * for the nav badge and the queue, and that query has to be company-scoped.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label');
            $table->string('approver_role')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['workflow_instance_id', 'position']);

            // "What is waiting on me", for the queue and the nav badge (CORE-WFL-008).
            $table->index(['company_id', 'approver_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
