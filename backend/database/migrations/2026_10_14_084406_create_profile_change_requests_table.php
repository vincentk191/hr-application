<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An employee's proposed change to their own profile, waiting for HR (EMP-CHG).
     *
     * `changes` holds each field as {before, after}: the "before" is what the employee saw when
     * they asked, which is how a later approval can tell that HR changed the field in between.
     * `conflicts` lists the fields an approval did not apply for that reason.
     */
    public function up(): void
    {
        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->json('changes');
            $table->json('conflicts')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
    }
};
