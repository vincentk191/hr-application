<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interview feedback (REC-INT): HR and the requisition's direct superior each give a candidate one evaluation after
     * the interview, a 1 to 5 rating, a recommendation and a note. One per person per candidate; it can be changed.
     */
    public function up(): void
    {
        Schema::create('candidate_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('recommendation', 12);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_evaluations');
    }
};
