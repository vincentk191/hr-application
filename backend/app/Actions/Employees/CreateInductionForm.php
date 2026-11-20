<?php

namespace App\Actions\Employees;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Employees\InductionFormPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files the "Formulir data induk karyawan" on a new employee's dossier: the company's form, filled with what is known, as a PDF.
 * The new employee prints it, completes it by hand, signs it and files the signed copy back (FileSignedCopy). Until that copy
 * is in, the document carries an expiry a week after it was made, so a form nobody returned shows up as expired for HR with
 * the reminders every expiring document already has.
 *
 * Safe to run twice for one employee (the queue may retry): a second run finds the form and changes nothing.
 */
class CreateInductionForm
{
    private const DISK = 'local';

    public function __construct(private InductionFormPdf $pdf) {}

    public function handle(Employee $employee): EmployeeDocument
    {
        $existing = EmployeeDocument::acrossCompanies()
            ->where('employee_id', $employee->id)
            ->where('kind', EmployeeDocument::KIND_INDUCTION_FORM)
            ->first();

        if ($existing instanceof EmployeeDocument) {
            return $existing;
        }

        $bytes = $this->pdf->render($employee);
        $path = "employee-documents/{$employee->id}/".Str::uuid().'.pdf';
        Storage::disk(self::DISK)->put($path, $bytes);

        return DB::transaction(function () use ($employee, $bytes, $path): EmployeeDocument {
            $document = EmployeeDocument::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'category' => DocumentCategory::Form,
                'kind' => EmployeeDocument::KIND_INDUCTION_FORM,
                'title' => 'Formulir data induk karyawan',
                // The employee must be able to open it to print it: a restricted document is closed even to its owner.
                'is_restricted' => false,
                'expires_on' => now()->addDays(EmployeeDocument::INDUCTION_FORM_DAYS)->toDateString(),
            ]);

            $document->versions()->create([
                'version_no' => 1,
                'path' => $path,
                'original_name' => 'Formulir-data-induk-'.Str::slug($employee->employee_number).'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'uploaded_by_user_id' => null,
                'note' => 'Dibuat otomatis saat karyawan ditambahkan.',
            ]);

            activity('documents')
                ->performedOn($document)
                ->withProperties(['employee' => $employee->full_name, 'expires_on' => $document->expires_on?->toDateString()])
                ->log('Formulir data induk dibuat otomatis');

            return $document;
        });
    }
}
