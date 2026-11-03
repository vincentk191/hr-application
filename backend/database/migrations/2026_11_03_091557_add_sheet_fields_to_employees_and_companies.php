<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns of HR's employee data sheet ("Lembar data field karyawan") that the dossier did not yet hold.
     *
     * All nullable, like the rest of the dossier: a record is creatable before anyone has chased every field. Nothing
     * here is unique: a family card number is shared by the whole household, so two employees may carry the same one.
     * The sheet's health data is not part of this; the existing health group is left as it was.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Personal
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('education_level')->nullable();
            $table->string('job_title')->nullable();

            // The home address: `address` holds the street, these the rest of it.
            $table->string('residence_village')->nullable();
            $table->string('residence_district')->nullable();
            $table->string('residence_city')->nullable();
            $table->string('residence_province')->nullable();
            $table->string('residence_postal_code', 10)->nullable();

            // The address on the KTP
            $table->text('id_address')->nullable();
            $table->string('id_block', 50)->nullable();
            $table->string('id_house_number', 50)->nullable();
            $table->string('id_rt', 10)->nullable();
            $table->string('id_rw', 10)->nullable();
            $table->string('id_village')->nullable();
            $table->string('id_district')->nullable();
            $table->string('id_city')->nullable();
            $table->string('id_province')->nullable();
            $table->string('id_postal_code', 10)->nullable();

            // Sensitive: seen and changed only with the sensitive-data permission
            $table->string('family_card_number', 20)->nullable();
            $table->string('mother_name')->nullable();
            $table->string('ptkp_status', 10)->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bpjs_employment_number', 50)->nullable();
            $table->string('bpjs_health_number', 50)->nullable();
            $table->string('employee_insurance')->nullable();
        });

        // The company's own tax numbers: the 15-digit NPWP and the 22-digit one (NITKU) the tax office now uses.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('npwp_15', 30)->nullable();
            $table->string('npwp_22', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['npwp_15', 'npwp_22']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'birth_place', 'birth_date', 'religion', 'marital_status', 'education_level', 'job_title',
                'residence_village', 'residence_district', 'residence_city', 'residence_province', 'residence_postal_code',
                'id_address', 'id_block', 'id_house_number', 'id_rt', 'id_rw', 'id_village', 'id_district', 'id_city',
                'id_province', 'id_postal_code',
                'family_card_number', 'mother_name', 'ptkp_status', 'bank_branch', 'bpjs_employment_number',
                'bpjs_health_number', 'employee_insurance',
            ]);
        });
    }
};
