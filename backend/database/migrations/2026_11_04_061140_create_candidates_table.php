<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 03, Screening (REC-SCR). Screening itself happens inside the job platforms, so the app keeps only what
     * HR types in: the **total number of applicants** on the requisition (a bare count, no personal data), and a row
     * for each candidate HR **shortlists**. Applicants who are not shortlisted are never stored.
     *
     * `phone_key` is the phone as bare digits in 62… form, so the same person typed two ways is recognised; the email
     * is kept lower-cased for the same reason.
     */
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->unsignedInteger('applicants_count')->nullable()->after('cancel_reason');
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('phone_key', 20)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('source', 20);
            $table->string('source_name', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_name')->nullable();
            $table->unsignedInteger('cv_size')->nullable();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('requisition_id');
            $table->index(['company_id', 'phone_key']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');

        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('applicants_count');
        });
    }
};
