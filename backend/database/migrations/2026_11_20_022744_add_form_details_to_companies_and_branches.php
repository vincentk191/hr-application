<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the printed "Formulir data induk karyawan" carries about the company that the app did not yet hold: its address and
     * tax method (Gross up or Netto), and for each Cabang the tax and BPJS numbers registered for that office (the form lists
     * every office and ticks the employee's). All optional: a company exists before anyone has these to hand, and the form
     * simply leaves a line empty.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
            $table->string('tax_method', 20)->nullable()->after('npwp_22');
        });

        Schema::table('branches', function (Blueprint $table) {
            // The 22-digit NITKU and the 15-digit NPWP of this office, as the tax office issued them.
            $table->string('nitku', 30)->nullable()->after('name');
            $table->string('npwp_15', 30)->nullable()->after('nitku');
            // The company's BPJS registration numbers for this office (not an employee's card number).
            $table->string('bpjs_employment_number', 50)->nullable()->after('npwp_15');
            $table->string('bpjs_health_number', 50)->nullable()->after('bpjs_employment_number');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['nitku', 'npwp_15', 'bpjs_employment_number', 'bpjs_health_number']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['address', 'tax_method']);
        });
    }
};
