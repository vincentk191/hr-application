<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one Google address a company's interview invitations may be sent from (REC-INT), set by an admin. Only that account
     * can be connected, so HR cannot send from another. A connection made before this existed keeps working: its account
     * becomes the company's sender address.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('google_sender_email')->nullable()->after('npwp_22');
        });

        DB::table('google_connections')->get()->each(function (object $connection): void {
            DB::table('companies')->where('id', $connection->company_id)->update(['google_sender_email' => strtolower($connection->google_email)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('google_sender_email');
        });
    }
};
