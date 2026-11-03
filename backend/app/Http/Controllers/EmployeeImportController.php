<?php

namespace App\Http\Controllers;

use App\Actions\Employees\ImportEmployees;
use App\Enums\Permission;
use App\Http\Requests\StoreEmployeeImportRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Support\CompanyContext;
use App\Support\Employees\EmployeeSheet;
use App\Support\Employees\EmployeeTemplateWorkbook;
use App\Support\Employees\InvalidEmployeeImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        $companyId = $this->companyId($request);

        return Inertia::render('employees/import', [
            'company' => $companyId === null ? null : Company::query()->whereKey($companyId)->value('name'),
            // The sensitive ones are marked: they are read only for someone with the sensitive-data permission.
            'columns' => array_map(fn (array $column): array => ['name' => $column[0], 'sensitive' => $column[2]], EmployeeSheet::COLUMNS),
            'canImportSensitive' => $request->user()->can(Permission::ViewSensitiveData->value),
            'maxRows' => ImportEmployees::MAX_ROWS,
            'result' => $request->session()->get('employee_import_result'),
        ]);
    }

    public function store(StoreEmployeeImportRequest $request, ImportEmployees $import): RedirectResponse
    {
        $companyId = $this->companyId($request);

        if ($companyId === null || ! $request->user()->canAccessCompany($companyId)) {
            throw ValidationException::withMessages(['file' => 'Pilih satu perusahaan di pemilih perusahaan sebelum mengimpor.']);
        }

        try {
            $result = $import->handle(
                $request->file('file')->getRealPath(),
                $companyId,
                $request->user(),
                $request->boolean('dry_run'),
            );
        } catch (InvalidEmployeeImport $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $result['errors'] = array_slice($result['errors'], 0, 100);

        return to_route('employees.import')->with('employee_import_result', $result);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorize('create', Employee::class);

        $book = (new EmployeeTemplateWorkbook)->build();

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, 'templat-karyawan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function companyId(Request $request): ?int
    {
        return app(CompanyContext::class)->companyId() ?? $request->user()->company_id;
    }
}
