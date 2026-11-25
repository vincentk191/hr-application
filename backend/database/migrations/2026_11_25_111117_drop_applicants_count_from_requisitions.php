<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The total number of applicants HR typed in per requisition is no longer kept or shown: only the shortlist counts.
     * The numbers already entered are deleted with the column.
     */
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('applicants_count');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->unsignedInteger('applicants_count')->nullable()->after('cancel_reason');
        });
    }
};
