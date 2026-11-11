<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An offer to a chosen candidate (REC-OFS): HR proposes it, Management approves it and sets the starting salary. Its
     * stage and its one-step approval chain live on its workflow instance, like a requisition's, so this table holds only
     * what the offer is about. A candidate can have several over time (a rejected proposal can be made again), at most
     * one of them live.
     */
    public function up(): void
    {
        Schema::create('candidate_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('salary')->nullable();
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable();
            $table->timestamps();

            $table->index(['requisition_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_offers');
    }
};
