<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fast-track (REC-WIZ-006): a requisition the requester marks "Mendesak" with a reason. It is only a marker: it sorts
     * first and its approvers are told it is urgent, but the approval chain is the same.
     */
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('draft_step');
            $table->string('urgent_reason', 300)->nullable()->after('is_urgent');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['is_urgent', 'urgent_reason']);
        });
    }
};
