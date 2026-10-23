<?php

namespace App\Http\Controllers;

use App\Actions\Employees\ImportEmployees;
use App\Http\Requests\StoreEmployeeImportRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Support\CompanyContext;
use App\Support\CsvDownload;
use App\Support\Employees\InvalidEmployeeImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    /**
     * Columns of the template and of the export, in order: the same set the importer reads, so an
     * exported list can be edited and loaded into another company.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'ID karyawan', 'Nama', 'Cabang', 'Departemen', 'Jabatan', 'ID atasan', 'Status', 'Tipe',
        'Tanggal bergabung', 'Tanggal keluar', 'Email', 'Telepon', 'Alamat', 'NIK',
    ];

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        $companyId = $this->companyId($request);

        return Inertia::render('employees/import', [
            'company' => $companyId === null ? null : Company::query()->whereKey($companyId)->value('name'),
            'columns' => self::COLUMNS,
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

        return CsvDownload::stream('templat-karyawan.csv', [
            self::COLUMNS,
            ['EMP-001', 'Budi Santoso', 'Pusat', 'Operasional', 'Staf', '', 'Aktif', 'Tetap', '2024-01-15', '', 'budi@example.com', '0812-0000-0000', 'Jl. Contoh No. 1', '3174000000000001'],
        ]);
    }

    private function companyId(Request $request): ?int
    {
        return app(CompanyContext::class)->companyId() ?? $request->user()->company_id;
    }
}
