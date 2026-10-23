<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An event can be narrowed to one Cabang, as it can to one department: a holiday that only one
     * city keeps. Empty means every Cabang, so every existing event keeps covering the same people.
     */
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('department_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
