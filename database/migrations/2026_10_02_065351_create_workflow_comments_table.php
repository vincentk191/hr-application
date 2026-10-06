<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The thread between requester and approver on an instance (CORE-WFL-007).
     *
     * Not company-tagged: comments are only ever read through their instance, which is scoped.
     */
    public function up(): void
    {
        Schema::create('workflow_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['workflow_instance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_comments');
    }
};
