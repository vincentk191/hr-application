<?php

namespace App\Concerns;

use App\Enums\DocumentCategory;
use Illuminate\Validation\Rule;

/**
 * The rules shared by the document forms (EMP-DOC-001, EMP-DOC-003).
 *
 * Documents live on a private disk and are only ever served through an authorised route, so the
 * allow-list below is about what HR may file, not about what is safe to serve.
 */
trait ValidatesEmployeeDocument
{
    /**
     * @return list<string>
     */
    protected function fileRules(): array
    {
        return ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'is_restricted' => ['nullable', 'boolean'],
            'expires_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function documentMessages(): array
    {
        return [
            'file.required' => 'Pilih berkas yang akan diunggah.',
            'file.mimes' => 'Berkas harus berformat PDF, gambar, Word, atau Excel.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
            'title.required' => 'Isi judul dokumen.',
            'category.required' => 'Pilih kategori dokumen.',
        ];
    }
}
