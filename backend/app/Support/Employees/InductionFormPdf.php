<?php

namespace App\Support\Employees;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The "Formulir data induk karyawan" as a PDF: the company's own form, filled with what is known about the new employee, to be
 * printed, completed by hand, signed and filed back (see CreateInductionForm).
 */
class InductionFormPdf
{
    public function __construct(private InductionFormData $data) {}

    public function render(Employee $employee): string
    {
        return Pdf::loadView('pdf.employee-induction-form', ['form' => $this->data->for($employee)])
            ->setPaper('a4', 'portrait')
            // Only the letters the text uses are put in the file: the whole font makes it close to a megabyte.
            ->setOption(['enable_font_subsetting' => true])
            ->output();
    }
}
