<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The master list of offboarding items belongs to the whole group, not to a company, because some
     * departments (IT, say) serve every company. An item can name the person who owns it; with no one named,
     * its owner is the supervisor of the department it names, in the leaver's company, and failing that HR.
     * So an item now carries a department by *name* instead of one company's department row.
     *
     * Items made per company are merged: the same item in several companies becomes one. A group on a case
     * now remembers who owns it (a person, with their job title and department as they were) and which master
     * items it was given, because an owner may belong to another company.
     */
    public function up(): void
    {
        Schema::table('offboarding_items', function (Blueprint $table) {
            $table->string('department_name', 100)->nullable()->after('kind');
            $table->foreignId('owner_employee_id')->nullable()->after('department_name')->constrained('employees')->nullOnDelete();
        });

        foreach (DB::table('offboarding_items')->whereNotNull('department_id')->get(['id', 'department_id']) as $item) {
            DB::table('offboarding_items')->where('id', $item->id)->update([
                'department_name' => DB::table('departments')->where('id', $item->department_id)->value('name'),
            ]);
        }

        $seen = [];

        foreach (DB::table('offboarding_items')->orderBy('id')->get() as $item) {
            $key = mb_strtolower($item->name).'|'.$item->kind.'|'.mb_strtolower((string) $item->department_name);

            if (! isset($seen[$key])) {
                $seen[$key] = $item->id;

                continue;
            }

            DB::table('offboarding_case_items')->where('offboarding_item_id', $item->id)->update(['offboarding_item_id' => $seen[$key]]);
            DB::table('offboarding_items')->where('id', $item->id)->delete();
        }

        Schema::table('offboarding_items', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_active']);
        });

        Schema::table('offboarding_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('department_id');
            $table->index('is_active');
        });

        Schema::table('offboarding_case_departments', function (Blueprint $table) {
            $table->string('owner_title', 150)->nullable();
            $table->string('owner_department', 150)->nullable();
            $table->json('item_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('offboarding_case_departments', function (Blueprint $table) {
            $table->dropColumn(['owner_title', 'owner_department', 'item_ids']);
        });

        Schema::table('offboarding_items', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropConstrainedForeignId('owner_employee_id');
            $table->dropColumn('department_name');
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
