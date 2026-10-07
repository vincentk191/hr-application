<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scanner punches, the enrol-ID mapping and the import log (ATT-DEV, ATTENDANCE-SYNC.md).
     *
     * Raw punches are append-only and de-duplicated on (device serial, scan time, PIN), the same
     * key as Personnel's own att_log, so a file imported twice, or a file and the sync agent
     * covering the same days, can never double-count. A punch whose PIN is not mapped to an
     * employee yet is never dropped: it waits in its own table until HR maps the PIN, then moves
     * across. Scan times are stored in UTC.
     */
    public function up(): void
    {
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('original_name')->nullable();
            $table->string('device_sn', 100);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('unmapped')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->timestamp('first_scan_at')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_enrol_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('device_sn', 100);
            $table->string('pin', 50);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['device_sn', 'pin']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('device_sn', 100);
            $table->string('pin', 50);
            $table->timestamp('scan_at');
            $table->smallInteger('verify_mode')->nullable();
            $table->smallInteger('inout_mode')->nullable();
            $table->string('source', 20);
            $table->foreignId('import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['device_sn', 'scan_at', 'pin']);
            $table->index(['employee_id', 'scan_at']);
            $table->index(['company_id', 'scan_at']);
        });

        Schema::create('attendance_unmapped_punches', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn', 100);
            $table->string('pin', 50);
            $table->timestamp('scan_at');
            $table->smallInteger('verify_mode')->nullable();
            $table->smallInteger('inout_mode')->nullable();
            $table->string('source', 20);
            $table->foreignId('import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['device_sn', 'scan_at', 'pin']);
            $table->index(['device_sn', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_unmapped_punches');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_enrol_maps');
        Schema::dropIfExists('attendance_imports');
    }
};
