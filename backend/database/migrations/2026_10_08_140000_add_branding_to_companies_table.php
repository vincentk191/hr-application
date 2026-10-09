<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company's own colour and logo. Both are optional: without them the app keeps its default
     * look. The colour for the combined "Semua perusahaan" view is a setting, not a column, because
     * it belongs to no company.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['brand_color', 'logo_path']);
        });
    }
};
