<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A request to hire for a role (REC-WIZ, REC-PIP). Its stage, approval chain and comments live on the
     * workflow instance; this row holds what is being asked for. The salary expectation is Management's,
     * set while approving (REC-APR-008), so it stays empty until then.
     */
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('position_name');
            $table->unsignedSmallInteger('quota')->nullable();
            $table->text('reason')->nullable();
            $table->date('target_start_on')->nullable();
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
            $table->unsignedTinyInteger('draft_step')->default(1);
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->foreignId('salary_set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
