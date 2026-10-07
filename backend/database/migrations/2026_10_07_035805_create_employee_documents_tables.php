<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee documents and their versions (EMP-DOC).
     *
     * A document is the stable thing HR files — "Kontrak kerja" — and each upload under it is a
     * version, so replacing a file keeps the old one (EMP-DOC-003). The metadata that decides
     * who may see it (restricted, EMP-DOC-004) and when it lapses (EMP-DOC-002) belongs to the
     * document, not to a version. Soft-deleted rather than deleted: the files stay on disk.
     */
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('title');
            $table->boolean('is_restricted')->default(false);
            $table->date('expires_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'category']);
            $table->index('expires_on');
        });

        Schema::create('employee_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_document_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_versions');
        Schema::dropIfExists('employee_documents');
    }
};
