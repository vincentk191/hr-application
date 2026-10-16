<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offboarding checklist (OFF-ITM, OFF-CHK).
     *
     * `offboarding_items` is the master list of what usually has to be handed back or switched off, each
     * belonging to a department. When a case reaches its checklist stage, every department with items
     * becomes a group on the case (`offboarding_case_departments`); the supervisor of that department
     * owns the group: picks which items apply (`offboarding_case_items`), confirms the list, then
     * ticks the items as they are done. A group's owner is stored when the group is made, so a later
     * change of supervisor does not move work that is already under way.
     */
    public function up(): void
    {
        Schema::create('offboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('kind', 20);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('offboarding_case_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('not_involved')->default(false);
            $table->timestamps();

            $table->index('offboarding_case_id');
        });

        Schema::create('offboarding_case_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_case_department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('kind', 20);
            $table->boolean('is_adhoc')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('offboarding_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_case_items');
        Schema::dropIfExists('offboarding_case_departments');
        Schema::dropIfExists('offboarding_items');
    }
};
