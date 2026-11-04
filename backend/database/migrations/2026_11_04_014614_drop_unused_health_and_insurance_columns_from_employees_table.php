<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Kesehatan & asuransi" section keeps only the two BPJS numbers (`bpjs_employment_number`,
     * `bpjs_health_number`); the rest of a person's health and cover data lives on the BPJS site itself.
     * These columns held the older health group (policy number and tier, blood type, height, weight, last medical
     * check, declared conditions) and the sheet's "Asuransi Karyawan". Their values are not carried anywhere else.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_number', 'insurance_tier', 'blood_type', 'height_cm', 'weight_kg',
                'last_medical_check_on', 'declared_conditions', 'employee_insurance',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('insurance_number')->nullable();
            $table->string('insurance_tier')->nullable();
            $table->string('blood_type')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->date('last_medical_check_on')->nullable();
            $table->text('declared_conditions')->nullable();
            $table->string('employee_insurance')->nullable();
        });
    }
};
