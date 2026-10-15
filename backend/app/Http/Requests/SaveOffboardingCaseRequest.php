<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\OffboardingExitType;
use App\Enums\OffboardingFileKind;
use App\Models\Employee;
use App\Models\OffboardingCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saving an offboarding case as a draft, new or changed (OFF-WIZ-002). A draft may be half-filled, so
 * nearly everything is optional here; sending it is where completeness is checked.
 */
class SaveOffboardingCaseRequest extends FormRequest
{
    public const FILE_RULES = ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'];

    private ?Employee $resolvedEmployee = null;

    public function authorize(): bool
    {
        $case = $this->route('offboardingCase');

        // More files on a case that is already running are allowed until it is closed.
        if ($case instanceof OffboardingCase && $this->routeIs('offboarding.files.store')) {
            return $this->user()?->can('addFiles', $case) === true;
        }

        return $case instanceof OffboardingCase
            ? $this->user()?->can('update', $case) === true
            : $this->user()?->can('create', OffboardingCase::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            // The employee is chosen once. Resolved through the company scope in after(), so a person
            // from another company simply does not exist here.
            'employee_id' => [$this->route('offboardingCase') === null ? 'required' : 'prohibited', 'integer'],
            'exit_type' => ['nullable', Rule::enum(OffboardingExitType::class)],
            'notice_on' => ['nullable', 'date_format:Y-m-d'],
            'last_working_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:notice_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'compensation_months' => ['nullable', 'integer', 'min:0', 'max:36'],
            'compensation_reason' => ['nullable', 'string', 'max:2000'],
            'send' => ['nullable', 'boolean'],
            'remove_files' => ['nullable', 'array'],
            'remove_files.*' => ['integer'],
        ];

        foreach (OffboardingFileKind::cases() as $kind) {
            $rules["file_{$kind->value}"] = ['nullable', 'array', 'max:5'];
            $rules["file_{$kind->value}.*"] = self::FILE_RULES;
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('offboardingCase') !== null || $validator->errors()->has('employee_id')) {
                    return;
                }

                $employee = $this->employee();

                if ($employee === null) {
                    $validator->errors()->add('employee_id', 'Karyawan tidak ditemukan.');
                } elseif ($employee->employment_status !== EmploymentStatus::Active) {
                    $validator->errors()->add('employee_id', 'Karyawan ini sudah tidak aktif.');
                } elseif ($employee->user_id !== null && $employee->user_id === $this->user()?->id) {
                    $validator->errors()->add('employee_id', 'Anda tidak dapat membuat kasus offboarding untuk diri sendiri.');
                } elseif (OffboardingCase::query()->open()->where('employee_id', $employee->id)->exists()) {
                    $validator->errors()->add('employee_id', 'Karyawan ini sudah punya kasus offboarding yang belum ditutup.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Pilih karyawan.',
            'last_working_on.after_or_equal' => 'Hari kerja terakhir tidak boleh sebelum tanggal pemberitahuan.',
            'compensation_months.max' => 'Kompensasi maksimal 36 bulan.',
            '*.mimes' => 'Berkas harus berformat PDF, JPG, PNG, DOC, atau DOCX.',
            '*.max' => 'Setiap berkas maksimal 10 MB.',
        ];
    }

    /**
     * The employee being offboarded, resolved through the company scope and memoised.
     */
    public function employee(): ?Employee
    {
        return $this->resolvedEmployee ??= Employee::find($this->integer('employee_id'));
    }

    /**
     * The case's own fields, as saved. Only those that were sent: a field left out is left alone, and
     * one sent empty is cleared.
     *
     * @return array<string, mixed>
     */
    public function caseFields(): array
    {
        $fields = array_intersect_key(
            $this->validated(),
            array_flip(['exit_type', 'notice_on', 'last_working_on', 'reason', 'compensation_months', 'compensation_reason']),
        );

        if (array_key_exists('compensation_months', $fields)) {
            $fields['compensation_months'] = (int) $fields['compensation_months'];
        }

        return $fields;
    }

    /**
     * The files sent with the form, by kind.
     *
     * @return list<array{kind: OffboardingFileKind, files: list<\Illuminate\Http\UploadedFile>}>
     */
    public function uploads(): array
    {
        $uploads = [];

        foreach (OffboardingFileKind::cases() as $kind) {
            $files = array_values((array) $this->file("file_{$kind->value}", []));

            if ($files !== []) {
                $uploads[] = ['kind' => $kind, 'files' => $files];
            }
        }

        return $uploads;
    }
}
