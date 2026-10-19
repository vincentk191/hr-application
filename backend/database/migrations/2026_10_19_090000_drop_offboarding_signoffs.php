<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-off no longer has signatures: HR, who asked for the case, fills in the exit details and closes it
     * (OFF-SIG-005 replaced). The Atasan and HR Admin no longer sign, so the table goes.
     */
    public function up(): void
    {
        Schema::dropIfExists('offboarding_signoffs');
    }

    public function down(): void
    {
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
};
