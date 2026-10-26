<?php

namespace App\Http\Requests;

use App\Enums\AttendanceSource;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRekapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance-device') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only a Cabang whose attendance comes as a rekapitulasi; HR sets that on the Cabang. The
            // rule reads the table directly, which is right here: the page is group-level.
            'branch_id' => ['required', 'integer', Rule::exists(Branch::class, 'id')->where('attendance_source', AttendanceSource::Rekap->value)],
            'file' => ['required', 'file', 'extensions:xls,xlsx', 'max:'.config('attendance.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Pilih cabang.',
            'branch_id.exists' => 'Cabang ini tidak memakai rekapitulasi. Ubah "Sumber absensi" cabang di panel admin bila perlu.',
            'file.required' => 'Pilih berkas rekapitulasi yang akan diunggah.',
            'file.extensions' => 'Berkas harus berformat Excel (.xls atau .xlsx).',
            'file.max' => 'Ukuran berkas terlalu besar.',
        ];
    }
}
