<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\ConfirmRekap;
use App\Actions\Attendance\ImportRekap;
use App\Enums\EmploymentStatus;
use App\Enums\RekapMatch;
use App\Enums\Zona;
use App\Http\Requests\StoreAttendanceRekapRequest;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Branch;
use App\Models\Employee;
use App\Support\Attendance\InvalidRekap;
use App\Support\Attendance\PotonganCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A branch's rekapitulasi, from upload to confirmation (ATT-REK). The upload becomes a draft; HR looks at
 * who each name was matched to, ties or skips the ones that found nobody, sees where the app's deduction
 * differs from the Cabang's, and only then confirms. Group-level like the rest of the data page.
 */
class AttendanceRekapController extends Controller
{
    public function store(StoreAttendanceRekapRequest $request, ImportRekap $import): RedirectResponse
    {
        $branch = Branch::acrossCompanies()->findOrFail($request->integer('branch_id'));

        try {
            $rekap = $import->handle($request->file('file'), $branch, $request->user());
        } catch (InvalidRekap $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return to_route('attendance.rekaps.show', $rekap->id);
    }

    public function show(int $rekap): Response
    {
        $this->authorize('manage-attendance-device');

        $rekap = $this->find($rekap);
        $rekap->load(['branch:id,name', 'user:id,name']);
        $rows = $rekap->rows()->with(['employee:id,full_name,employee_number,zona'])->get();
        $draft = $rekap->status === AttendanceRekap::STATUS_DRAFT;
        $counted = $rows->filter(fn (AttendanceRekapRow $row): bool => $row->match_status !== RekapMatch::Skipped);
        $lines = $rows->map(fn (AttendanceRekapRow $row): array => $this->line($row))->values();

        return Inertia::render('attendance/rekaps/show', [
            'rekap' => [
                'id' => $rekap->id,
                'status' => $rekap->status,
                'file' => $rekap->original_name,
                'branch' => $rekap->branch?->name,
                'fileBranch' => $rekap->file_branch_name,
                'branchMismatch' => $rekap->file_branch_name !== null
                    && PotonganCalculator::normalise($rekap->file_branch_name) !== PotonganCalculator::normalise($rekap->branch?->name),
                'periodStart' => $rekap->period_start->toDateString(),
                'periodEnd' => $rekap->period_end->toDateString(),
                'periodLabel' => $rekap->period_label,
                'by' => $rekap->user?->name,
                'confirmedAt' => $rekap->confirmed_at?->toIso8601String(),
            ],
            'rows' => $lines,
            'summary' => [
                'total' => $rows->count(),
                'matched' => $rows->where('match_status', RekapMatch::Matched)->count(),
                'needAttention' => $rows->filter(fn (AttendanceRekapRow $row): bool => $row->match_status->needsAttention())->count(),
                'skipped' => $rows->where('match_status', RekapMatch::Skipped)->count(),
                'zonaMismatches' => $lines->where('zonaMismatch', true)->count(),
                'potonganMismatches' => $lines->where('potonganMismatch', true)->count(),
                'fileTotal' => (int) $counted->sum('file_potongan'),
                'computedTotal' => (int) $counted->sum('computed_potongan'),
            ],
            'notInFile' => $draft ? $this->notInFile($rekap, $rows) : [],
            'replaces' => $draft && $this->identical($rekap)->exists(),
            'overlaps' => $draft ? $this->overlapping($rekap) : [],
            'employees' => $draft ? $this->companyEmployees($rekap) : [],
        ]);
    }

    /**
     * Tie a line to an employee of the Cabang, or leave it out.
     */
    public function linkRow(Request $request, int $rekap, int $row, ImportRekap $import): RedirectResponse
    {
        $this->authorize('manage-attendance-device');

        $rekap = $this->find($rekap);
        $line = $rekap->rows()->whereKey($row)->firstOrFail();

        abort_unless($rekap->status === AttendanceRekap::STATUS_DRAFT, 409, 'Rekapitulasi ini sudah dikonfirmasi.');

        $data = $request->validate([
            'skip' => ['nullable', 'boolean'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        if ($request->boolean('skip')) {
            $line->match_status = RekapMatch::Skipped;
            $import->tie($line, null, $rekap->period_label);

            return back();
        }

        $employee = Employee::acrossCompanies()
            ->where('company_id', $rekap->company_id)
            ->with('position:id,name')
            ->find($data['employee_id'] ?? null);

        if ($employee === null) {
            throw ValidationException::withMessages(['employee_id' => 'Pilih karyawan dari perusahaan ini.']);
        }

        if ($rekap->rows()->where('employee_id', $employee->id)->whereKeyNot($line->id)->exists()) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} sudah terhubung ke baris lain."]);
        }

        $line->match_status = RekapMatch::Matched;
        $import->tie($line, $employee, $rekap->period_label);

        return back();
    }

    public function confirm(int $rekap, ConfirmRekap $confirm): RedirectResponse
    {
        $this->authorize('manage-attendance-device');

        try {
            $confirm->handle($this->find($rekap));
        } catch (InvalidRekap $exception) {
            throw ValidationException::withMessages(['confirm' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rekapitulasi dikonfirmasi.']);

        return back();
    }

    public function destroy(int $rekap): RedirectResponse
    {
        $this->authorize('manage-attendance-device');

        $rekap = $this->find($rekap);

        abort_unless($rekap->status === AttendanceRekap::STATUS_DRAFT, 409, 'Hanya draf yang dapat dibuang.');

        $rekap->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draf rekapitulasi dibuang.']);

        return to_route('attendance.device', ['tab' => 'rekap']);
    }

    private function find(int $id): AttendanceRekap
    {
        return AttendanceRekap::acrossCompanies()->findOrFail($id);
    }

    /**
     * One line of the preview, with the checks against the employee's profile and the Cabang's own total.
     *
     * @return array<string, mixed>
     */
    private function line(AttendanceRekapRow $row): array
    {
        $employee = $row->employee;
        $counted = $row->match_status !== RekapMatch::Skipped;
        $fileZona = Zona::tryFrom(strtolower((string) $row->zona_file));

        return [
            'id' => $row->id,
            'number' => $row->row_number,
            'name' => $row->name,
            'position' => $row->position_text,
            'zonaFile' => $row->zona_file,
            'zonaApp' => $employee?->zona->label(),
            // A Zona in the file that is not one of the three, or one that differs from the profile.
            'zonaMismatch' => $counted && ($row->zona_file !== null && ($fileZona === null || ($employee !== null && $fileZona !== $employee->zona))),
            'alpa' => $row->alpa,
            'izin' => $row->izin,
            'sakit' => $row->sakit,
            'telat' => $row->telat,
            'filePotongan' => $row->file_potongan,
            'computedPotongan' => $row->computed_potongan,
            'potonganMismatch' => $counted && $row->file_potongan !== null && $row->file_potongan !== $row->computed_potongan,
            'exempt' => $row->exempt,
            'status' => $row->match_status->value,
            'statusLabel' => $row->match_status->label(),
            'employeeId' => $row->employee_id,
            'employee' => $employee === null ? null : "{$employee->full_name} ({$employee->employee_number})",
        ];
    }

    /**
     * Active employees of the Cabang who are not in the file, so HR can see who was left off.
     *
     * @param  Collection<int, AttendanceRekapRow>  $rows
     * @return list<string>
     */
    private function notInFile(AttendanceRekap $rekap, $rows): array
    {
        $listed = $rows->pluck('employee_id')->filter()->all();

        return Employee::acrossCompanies()
            ->where('branch_id', $rekap->branch_id)
            ->where('employment_status', EmploymentStatus::Active->value)
            ->whereNotIn('id', $listed)
            ->orderBy('full_name')
            ->pluck('full_name')
            ->all();
    }

    /**
     * @return Builder<AttendanceRekap>
     */
    private function identical(AttendanceRekap $rekap)
    {
        return AttendanceRekap::acrossCompanies()
            ->where('branch_id', $rekap->branch_id)
            ->where('status', AttendanceRekap::STATUS_CONFIRMED)
            ->whereDate('period_start', $rekap->period_start->toDateString())
            ->whereDate('period_end', $rekap->period_end->toDateString());
    }

    /**
     * Confirmed files of the Cabang that cover some of the same days without being the same dates:
     * both would count those days.
     *
     * @return list<array{id: int, periodStart: string, periodEnd: string}>
     */
    private function overlapping(AttendanceRekap $rekap): array
    {
        return AttendanceRekap::acrossCompanies()
            ->where('branch_id', $rekap->branch_id)
            ->where('status', AttendanceRekap::STATUS_CONFIRMED)
            ->whereDate('period_start', '<=', $rekap->period_end->toDateString())
            ->whereDate('period_end', '>=', $rekap->period_start->toDateString())
            ->get()
            ->reject(fn (AttendanceRekap $other): bool => $other->period_start->isSameDay($rekap->period_start) && $other->period_end->isSameDay($rekap->period_end))
            ->map(fn (AttendanceRekap $other): array => [
                'id' => $other->id,
                'periodStart' => $other->period_start->toDateString(),
                'periodEnd' => $other->period_end->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Who a line can be tied to by hand: anyone in the company, the Cabang's own people first. A name that
     * found nobody in the Cabang is usually someone whose profile is still filed under another Cabang, so
     * the list must reach them; the Cabang each one is filed under is shown.
     *
     * @return list<array{id: int, label: string}>
     */
    private function companyEmployees(AttendanceRekap $rekap): array
    {
        return Employee::acrossCompanies()
            ->where('company_id', $rekap->company_id)
            ->with('branch:id,name')
            ->orderBy('full_name')
            ->get(['id', 'branch_id', 'full_name', 'employee_number', 'employment_status'])
            ->sortBy(fn (Employee $employee): int => $employee->branch_id === $rekap->branch_id ? 0 : 1)
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'label' => "{$employee->full_name} ({$employee->employee_number})"
                    .($employee->branch_id === $rekap->branch_id ? '' : " · {$employee->branch?->name}")
                    .($employee->employment_status === EmploymentStatus::Active ? '' : ' · nonaktif'),
            ])
            ->values()
            ->all();
    }
}
