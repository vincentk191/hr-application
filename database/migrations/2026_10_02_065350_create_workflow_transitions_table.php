<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every stage change, in order (CORE-WFL-005/006).
     *
     * Kept as its own table rather than read back out of the activity log, because
     * time-in-stage and the stage-by-stage timeline on close (OFF-PIP-007) are ordinary
     * queries over this, and reconstructing them from a generic log would not be.
     *
     * Not company-tagged: rows are only ever read through their instance, which is scoped.
     */
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['workflow_instance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
