<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company's 16-digit NPWP, beside the 15-digit and the 22-digit (NITKU) numbers it already holds. Nullable like the
     * others: a company exists before anyone has its numbers to hand, and the employee import fills a blank one.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('npwp_16', 30)->nullable()->after('npwp_15');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('npwp_16');
        });
    }
};
