<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offer letter as a PDF and what comes back (email automation): the PDF made when the offer is sent, which is what the
     * candidate holds and stays as it was; the email that carried it (so HR sees whether it went); and the signed scan the
     * candidate returns, filed by HR.
     */
    public function up(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->string('letter_pdf_path')->nullable()->after('letter_snapshot');
            $table->foreignId('email_message_id')->nullable()->after('emailed_at')->constrained('email_messages')->nullOnDelete();
            $table->string('signed_letter_path')->nullable()->after('decline_reason');
            $table->string('signed_letter_name')->nullable()->after('signed_letter_path');
            $table->unsignedInteger('signed_letter_size')->nullable()->after('signed_letter_name');
            $table->timestamp('signed_letter_at')->nullable()->after('signed_letter_size');
            $table->foreignId('signed_letter_by_user_id')->nullable()->after('signed_letter_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_message_id');
            $table->dropConstrainedForeignId('signed_letter_by_user_id');
            $table->dropColumn(['letter_pdf_path', 'signed_letter_path', 'signed_letter_name', 'signed_letter_size', 'signed_letter_at']);
        });
    }
};
