<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The four card groups on the profile Overview tab (EMP-DET, context.md §10).
     *
     * All nullable: these are filled in over time, and the initial employee import brings only
     * names and numbers. Nothing here is required to create a record (EMP-ADD-002).
     *
     * Money is **integer rupiah**, never a float — the project rule. Rupiah has no minor unit
     * in practice, so a plain integer is the honest representation and avoids every rounding
     * question a decimal invites.
     *
     * Health and insurance (EMP-DET-005) is new scope recorded for the group health policy,
     * and the most sensitive group here; EMP-DET-006 puts these behind the same per-document
     * access control as Documents, which lands with that screen.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Contact + emergency contact (EMP-DET-002)
            $table->string('email')->nullable()->after('full_name');
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('national_id')->nullable()->after('address');
            $table->string('emergency_contact_name')->nullable()->after('national_id');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_phone');

            // Bank and tax (EMP-DET-003)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('payment_method')->nullable();

            // Payroll (EMP-DET-004)
            $table->string('pay_group')->nullable();
            $table->unsignedBigInteger('base_salary_idr')->nullable();
            $table->unsignedBigInteger('allowances_idr')->nullable();
            $table->unsignedBigInteger('overtime_rate_idr')->nullable();

            // Health and insurance (EMP-DET-005)
            $table->string('insurance_number')->nullable();
            $table->string('insurance_tier')->nullable();
            $table->string('blood_type')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->date('last_medical_check_on')->nullable();
            $table->text('declared_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'address',
                'national_id',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'tax_number',
                'payment_method',
                'pay_group',
                'base_salary_idr',
                'allowances_idr',
                'overtime_rate_idr',
                'insurance_number',
                'insurance_tier',
                'blood_type',
                'height_cm',
                'weight_kg',
                'last_medical_check_on',
                'declared_conditions',
            ]);
        });
    }
};
