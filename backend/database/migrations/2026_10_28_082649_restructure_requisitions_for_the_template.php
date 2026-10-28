<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A requisition follows a fixed template (REC-WZ1, REC-WZ2): the contract type and length, the direct
     * superior, and the job posting and candidate checklist as structured lists in place of a single description and
     * a flat requirement list. The earlier first cut of the requisition had only been used for testing, so those two
     * columns are dropped without being carried over.
     */
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('employment_type', 20)->nullable()->after('quota');
            $table->unsignedTinyInteger('contract_months')->nullable()->after('employment_type');
            $table->foreignId('manager_employee_id')->nullable()->after('contract_months')->constrained('employees')->nullOnDelete();
            $table->text('about')->nullable()->after('target_start_on');
            $table->json('responsibilities')->nullable()->after('about');
            $table->json('benefits')->nullable()->after('responsibilities');
            $table->json('checklist')->nullable()->after('benefits');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['description', 'requirements']);
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_employee_id');
            $table->dropColumn(['employment_type', 'contract_months', 'about', 'responsibilities', 'benefits', 'checklist']);
        });
    }
};
