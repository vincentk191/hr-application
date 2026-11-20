<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a document the app made itself and expects back signed: today the "Formulir data induk karyawan" made when an
     * employee is added. `kind` says which one, so the right screen and the right permission apply to it and to nothing else;
     * `signed_at` is when the signed copy was filed, after which it no longer waits and no longer expires.
     */
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('kind', 30)->nullable()->after('category');
            $table->timestamp('signed_at')->nullable()->after('expires_on');
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropColumn(['kind', 'signed_at']);
        });
    }
};
