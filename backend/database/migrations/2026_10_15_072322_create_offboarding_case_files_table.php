<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The files on a case (OFF-WZ1-006, OFF-PIP-006): the resignation letter, notices, handover notes,
     * return evidence and, later, the generated berita acara. Kept on the private disk under a random
     * name; the name the person gave is only for display.
     */
    public function up(): void
    {
        Schema::create('offboarding_case_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offboarding_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['offboarding_case_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_case_files');
    }
};
