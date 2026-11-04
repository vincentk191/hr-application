<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 02, Posting (REC-PST): HR posts the listing on each job platform by hand and records where and when.
     * One row per platform; "Lainnya" carries its own name. The platforms are on free accounts, so nothing is
     * pushed or imported: this table is HR's record. A requisition that is called off keeps its reason (REC-PST-004).
     */
    public function up(): void
    {
        Schema::create('requisition_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('platform_name', 60)->nullable();
            $table->string('url', 500)->nullable();
            $table->date('posted_on');
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('requisition_id');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });

        Schema::dropIfExists('requisition_postings');
    }
};
