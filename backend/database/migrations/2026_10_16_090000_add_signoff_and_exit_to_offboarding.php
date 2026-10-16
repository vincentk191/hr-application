<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-off, the exit interview, closing and cancelling an offboarding case (OFF-SIG, OFF-CHK-010).
     *
     * `offboarding_signoffs` is the sequence of signatures: the Atasan (when there is a usable one), then HR,
     * each locked until the one before it has signed. The exit is applied once, on the day after the last
     * working day (`exit_applied_at`): the account is switched off, requests still waiting are cleared and
     * the scanner mapping ends. A case can be cancelled until then.
     */
    public function up(): void
    {
        Schema::table('offboarding_cases', function (Blueprint $table) {
            $table->text('exit_interview_reason')->nullable();
            $table->boolean('exit_interview_rehire')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->text('exit_interview_skipped_reason')->nullable();
            $table->timestamp('exit_interview_at')->nullable();
            $table->timestamp('exit_applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('offboarding_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('label', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['offboarding_case_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_signoffs');

        Schema::table('offboarding_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn([
                'exit_interview_reason', 'exit_interview_rehire', 'exit_interview_notes', 'exit_interview_skipped_reason',
                'exit_interview_at', 'exit_applied_at', 'cancelled_at', 'cancel_reason',
            ]);
        });
    }
};
