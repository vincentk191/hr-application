<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rekapitulasi a branch without a scanner sends for a period: counts of Alpa, Izin, Sakit and
     * Telat per employee. An upload is a draft until HR has looked at the preview and confirmed it;
     * confirming a second file for the same Cabang and dates replaces the first.
     */
    public function up(): void
    {
        Schema::create('attendance_rekaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('status')->default('draft');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_label')->nullable();
            $table->string('file_branch_name')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'period_start']);
        });

        Schema::create('attendance_rekap_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_rekap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('row_number');
            $table->string('name');
            $table->string('position_text')->nullable();
            $table->string('zona_file')->nullable();
            $table->unsignedSmallInteger('alpa')->default(0);
            $table->unsignedSmallInteger('izin')->default(0);
            $table->unsignedSmallInteger('sakit')->default(0);
            $table->unsignedSmallInteger('telat')->default(0);
            $table->unsignedInteger('file_potongan')->nullable();
            $table->unsignedInteger('computed_potongan')->default(0);
            $table->boolean('exempt')->default(false);
            $table->string('match_status')->default('unmatched');
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rekap_rows');
        Schema::dropIfExists('attendance_rekaps');
    }
};
