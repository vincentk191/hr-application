<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many scanner PINs an import linked to employees by their NIP, so the history shows it.
     */
    public function up(): void
    {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->unsignedInteger('auto_mapped')->default(0)->after('rejected');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->dropColumn('auto_mapped');
        });
    }
};
