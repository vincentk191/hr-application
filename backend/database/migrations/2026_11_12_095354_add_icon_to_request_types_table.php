<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The icon an administrator picked for a kind of request. Null means none was picked, and the screen guesses one from the
     * name as it always has, so nothing changes for a type until someone chooses.
     */
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->string('icon', 30)->nullable()->after('absence_kind');
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
