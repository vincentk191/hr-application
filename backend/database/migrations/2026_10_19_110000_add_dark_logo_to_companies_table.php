<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second logo for dark mode. Optional: without it the light logo is used in both modes.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_dark_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo_dark_path');
        });
    }
};
