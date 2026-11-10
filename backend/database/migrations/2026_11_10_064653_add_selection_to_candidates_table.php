<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The candidate the new hire's direct superior prefers (REC-PIP): when and who. Several may be chosen when the
     * requisition has more than one place. A choice is only a choice: Management's approval and the offer itself come
     * after it and are kept on their own records.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->timestamp('selected_at')->nullable()->after('rejected_by_user_id');
            $table->foreignId('selected_by_user_id')->nullable()->after('selected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_by_user_id');
            $table->dropColumn('selected_at');
        });
    }
};
