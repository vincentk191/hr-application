<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holidays and company events (ATT-WIZ-001): days on which attendance is not checked.
     *
     * An event applies to everyone when both company and department are empty, to one company when
     * only the company is set, and to one department when both are. company_id is nullable on
     * purpose — a group-wide public holiday belongs to no single company — which is why this table
     * is not company-scoped like the rest.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
