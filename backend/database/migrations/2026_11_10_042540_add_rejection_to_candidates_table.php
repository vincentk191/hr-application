<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A candidate HR turns down (REC-CAN-003): when, why and who. The row is kept, not deleted, so the requisition still
     * shows who was shortlisted and what became of them, and HR can take the decision back. Everything else about where a
     * candidate stands (invited, interviewed, offered, hired) is read from their own records, not stored here.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('form_data');
            $table->text('rejected_reason')->nullable()->after('rejected_at');
            $table->foreignId('rejected_by_user_id')->nullable()->after('rejected_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'rejected_reason']);
        });
    }
};
