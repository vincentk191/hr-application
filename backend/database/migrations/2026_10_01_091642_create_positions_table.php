<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job roles per department (CORE-ORG-002). Distinct from the RBAC roles that
     * spatie/laravel-permission manages: a position is an org-chart job title, used by the
     * dossier, requisitions and offboarding item owners.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
