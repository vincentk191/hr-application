<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far each person has read a workflow's comment thread, so the button above a case can say how
     * many comments are new to them, and the stage tabs how many were left in each stage (CORE-WFL-007).
     * One row per person per workflow instance, holding the newest comment they have seen.
     */
    public function up(): void
    {
        Schema::create('workflow_comment_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_comment_id')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'workflow_instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_comment_reads');
    }
};
