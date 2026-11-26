<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Mendesak" marker and its reason are gone: the target start date, with the reminders leading up to it, says when
     * a role is needed. The markers already set are deleted with the columns.
     */
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['is_urgent', 'urgent_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('draft_step');
            $table->string('urgent_reason', 300)->nullable()->after('is_urgent');
        });
    }
};
