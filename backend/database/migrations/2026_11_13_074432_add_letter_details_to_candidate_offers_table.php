<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What HR adds to one offer's letter (the letter number and an extra note, both optional), and the letter as it was
     * written when the offer was marked sent, so a later change to the template never rewrites a letter the candidate has.
     */
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->string('letter_number', 60)->nullable()->after('probation_months');
            $table->text('letter_note')->nullable()->after('letter_number');
            $table->json('letter_snapshot')->nullable()->after('emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->dropColumn(['letter_number', 'letter_note', 'letter_snapshot']);
        });
    }
};
