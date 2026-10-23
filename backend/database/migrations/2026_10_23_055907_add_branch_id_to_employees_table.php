<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every employee belongs to a Cabang. Existing employees cannot be placed by guessing, so each
     * company gets one default Cabang, "Pusat", and everyone already there is put in it; the System
     * Admin renames it and HR moves people afterwards. A company with no employees gets one too,
     * because nobody can be added to a company that has no Cabang.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained();
        });

        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $branchId = DB::table('branches')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Pusat',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('employees')->where('company_id', $companyId)->update(['branch_id' => $branchId]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
