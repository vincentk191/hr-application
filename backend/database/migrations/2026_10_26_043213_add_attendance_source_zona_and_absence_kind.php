<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three tags the attendance recap needs: where a Cabang's attendance comes from, the Zona that sets
     * an employee's deduction rate (Hijau until HR says otherwise), and whether a kind of request counts
     * as izin, sakit or neither. The seeded "Cuti sakit" is the sakit one.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('attendance_source')->default('scanner')->after('name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('zona')->default('hijau')->after('notice_days');
        });

        Schema::table('request_types', function (Blueprint $table) {
            $table->string('absence_kind')->default('none')->after('label');
        });

        DB::table('request_types')->where('code', 'sick')->update(['absence_kind' => 'sakit']);
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('absence_kind');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('zona');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('attendance_source');
        });
    }
};
