<?php

namespace App\Models;

use App\Enums\RekapMatch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's line in a rekapitulasi: the counts the Cabang reported, what the app works the
 * deduction out to, and which employee of the Cabang it was tied to. Reached through its
 * AttendanceRekap, which carries the company.
 *
 * @property int $id
 * @property int $attendance_rekap_id
 * @property int|null $employee_id
 * @property int $row_number
 * @property string $name
 * @property string|null $position_text
 * @property string|null $zona_file
 * @property int $alpa
 * @property int $izin
 * @property int $sakit
 * @property int $telat
 * @property int|null $file_potongan the TOTAL POTONGAN the Cabang wrote, in rupiah
 * @property int $computed_potongan what the app works out, in rupiah
 * @property bool $exempt no deduction applies (Supervisor, or a yearly period)
 * @property RekapMatch $match_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'attendance_rekap_id', 'employee_id', 'row_number', 'name', 'position_text', 'zona_file', 'alpa', 'izin',
    'sakit', 'telat', 'file_potongan', 'computed_potongan', 'exempt', 'match_status',
])]
class AttendanceRekapRow extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alpa' => 'integer',
            'izin' => 'integer',
            'sakit' => 'integer',
            'telat' => 'integer',
            'file_potongan' => 'integer',
            'computed_potongan' => 'integer',
            'exempt' => 'boolean',
            'match_status' => RekapMatch::class,
        ];
    }

    /**
     * @return BelongsTo<AttendanceRekap, $this>
     */
    public function rekap(): BelongsTo
    {
        return $this->belongsTo(AttendanceRekap::class, 'attendance_rekap_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }
}
