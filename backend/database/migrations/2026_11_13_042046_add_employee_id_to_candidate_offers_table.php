<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The employee profile made from an accepted offer (REC-FIL-006/007). One offer makes one profile, so the link is unique,
     * and it is what stops a hire being converted twice. The profile can be removed without losing the offer.
     */
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
