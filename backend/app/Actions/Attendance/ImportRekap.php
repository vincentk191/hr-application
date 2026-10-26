<?php

namespace App\Actions\Attendance;

use App\Enums\EmploymentStatus;
use App\Enums\RekapMatch;
use App\Enums\Zona;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\InvalidRekap;
use App\Support\Attendance\ParsedRekap;
use App\Support\Attendance\PotonganCalculator;
use App\Support\Attendance\RekapParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads a branch's rekapitulasi into a draft for HR to check. Nothing counts yet: the draft shows who in
 * the Cabang each name was tied to, which names found nobody, and where the app's deduction differs from
 * the one the Cabang wrote. Confirming it is a separate step (ConfirmRekap).
 *
 * Names are matched within the chosen Cabang only, so two branches may each have a "Budi".
 */
class ImportRekap
{
    public function __construct(
        private RekapParser $parser,
        private PotonganCalculator $potongan,
    ) {}

    /**
     * @throws InvalidRekap when the file is not a readable rekapitulasi
     */
    public function handle(UploadedFile $file, Branch $branch, User $by): AttendanceRekap
    {
        $parsed = $this->parser->parseFile((string) $file->getRealPath());

        return DB::transaction(function () use ($parsed, $file, $branch, $by): AttendanceRekap {
            $rekap = AttendanceRekap::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'user_id' => $by->id,
                'original_name' => $file->getClientOriginalName(),
                'status' => AttendanceRekap::STATUS_DRAFT,
                'period_start' => $parsed->periodStart,
                'period_end' => $parsed->periodEnd,
                'period_label' => $parsed->periodLabel,
                'file_branch_name' => $parsed->branchName,
            ]);

            $this->storeRows($rekap, $parsed, $branch);

            return $rekap;
        });
    }

    private function storeRows(AttendanceRekap $rekap, ParsedRekap $parsed, Branch $branch): void
    {
        $byName = Employee::acrossCompanies()
            ->where('branch_id', $branch->id)
            ->with('position:id,name')
            ->get()
            ->groupBy(fn (Employee $employee): string => PotonganCalculator::normalise($employee->full_name));

        $taken = [];

        foreach ($parsed->lines as $line) {
            [$employee, $status] = $this->match($byName->get(PotonganCalculator::normalise($line['name'])), $taken);

            if ($employee !== null) {
                $taken[$employee->id] = true;
            }

            $row = new AttendanceRekapRow([
                'attendance_rekap_id' => $rekap->id,
                'row_number' => $line['number'],
                'name' => $line['name'],
                'position_text' => $line['position'],
                'zona_file' => $line['zona'],
                'alpa' => $line['alpa'],
                'izin' => $line['izin'],
                'sakit' => $line['sakit'],
                'telat' => $line['telat'],
                'file_potongan' => $line['potongan'],
                'match_status' => $status,
            ]);

            $this->tie($row, $employee, $parsed->periodLabel);
        }
    }

    /**
     * The one employee a name belongs to, if there is exactly one. Of several with the same name, only
     * the active ones are considered; one already tied to an earlier line of this file is not offered
     * again, so a duplicated line cannot count a person twice.
     *
     * @param  Collection<int, Employee>|null  $candidates
     * @param  array<int, true>  $taken
     * @return array{0: Employee|null, 1: RekapMatch}
     */
    private function match(?Collection $candidates, array $taken): array
    {
        $candidates = ($candidates ?? collect())->reject(fn (Employee $employee): bool => isset($taken[$employee->id]));

        if ($candidates->count() > 1) {
            $candidates = $candidates->filter(fn (Employee $employee): bool => $employee->employment_status === EmploymentStatus::Active);
        }

        return match ($candidates->count()) {
            0 => [null, RekapMatch::Unmatched],
            1 => [$candidates->first(), RekapMatch::Matched],
            default => [null, RekapMatch::Ambiguous],
        };
    }

    /**
     * Tie a line to an employee (or to nobody) and work out its deduction. The employee's own Zona and
     * position decide it, because the profile is the record; the file's Zona is only a check against it.
     * A line with nobody tied yet is worked out from what the file says.
     */
    public function tie(AttendanceRekapRow $row, ?Employee $employee, ?string $periodLabel): AttendanceRekapRow
    {
        $row->employee_id = $employee?->id;

        $zona = $employee?->zona ?? Zona::tryFrom(strtolower((string) $row->zona_file)) ?? Zona::Hijau;
        $exempt = $this->potongan->isExempt($employee?->position?->name, $periodLabel)
            || $this->potongan->isExempt($row->position_text, $periodLabel);

        $row->exempt = $exempt;
        $row->computed_potongan = $exempt ? 0 : $this->potongan->amount($zona, $row->alpa, $row->izin, $row->telat);
        $row->save();

        return $row;
    }
}
