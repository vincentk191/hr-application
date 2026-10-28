<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Support\Recruitment\RequisitionDepartments;
use App\Support\Recruitment\RequisitionTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The requisition wizard's fields (REC-WZ1, REC-WZ2), saved as a draft or sent. A draft may be half-filled, so only
 * the position name and the department are needed to save one (REC-WZ1-002); sending checks the rest (SubmitRequisition).
 * The job posting and the candidate checklist follow the template's fixed sections (RequisitionTemplate).
 */
class SaveRequisitionRequest extends FormRequest
{
    public const MAX_SUBTITLES = 12;

    public const MAX_TITLE_LENGTH = 80;

    /** How far ahead a start date may be set: a typo such as 2062 is refused instead of saved. */
    public const MAX_YEARS_AHEAD = 2;

    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition
            ? $this->user()?->can('update', $requisition) === true
            : $this->user()?->can('create', Requisition::class) === true;
    }

    /**
     * Blank lines are dropped before validating, so an empty "+ Tambah field" row is not an error, and a subtitle
     * with neither a title nor a line is dropped whole.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_array($this->input('benefits'))) {
            $merge['benefits'] = RequisitionTemplate::clean($this->input('benefits'));
        }

        if (is_array($this->input('checklist'))) {
            $merge['checklist'] = array_map(fn (mixed $lines): array => RequisitionTemplate::clean($lines), $this->input('checklist'));
        }

        if (is_array($this->input('responsibilities'))) {
            $merge['responsibilities'] = $this->cleanResponsibilities($this->input('responsibilities'));
        }

        $this->merge($merge);
    }

    /**
     * @param  array<mixed>  $subtitles
     * @return list<array{title: string, items: list<string>}>
     */
    private function cleanResponsibilities(array $subtitles): array
    {
        $kept = [];

        foreach ($subtitles as $subtitle) {
            if (! is_array($subtitle)) {
                continue;
            }

            $title = is_scalar($subtitle['title'] ?? null) ? trim((string) $subtitle['title']) : '';
            $items = RequisitionTemplate::clean($subtitle['items'] ?? []);

            if ($title !== '' || $items !== []) {
                $kept[] = ['title' => $title, 'items' => $items];
            }
        }

        return $kept;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $line = ['string', 'max:'.RequisitionTemplate::MAX_LINE_LENGTH];
        $rules = [
            'department_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'position_name' => ['required', 'string', 'max:120'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:999'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'manager_employee_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'target_start_on' => ['nullable', 'date_format:Y-m-d'],
            'about' => ['nullable', 'string', 'max:3000'],
            'responsibilities' => ['nullable', 'array', 'max:'.self::MAX_SUBTITLES],
            'responsibilities.*.title' => ['nullable', 'string', 'max:'.self::MAX_TITLE_LENGTH],
            'responsibilities.*.items' => ['array', 'max:'.RequisitionTemplate::MAX_LINES],
            'responsibilities.*.items.*' => $line,
            'benefits' => ['nullable', 'array', 'max:'.RequisitionTemplate::MAX_LINES],
            'benefits.*' => $line,
            'checklist' => ['nullable', 'array'],
            'draft_step' => ['nullable', 'integer', 'in:1,2,3'],
            'send' => ['nullable', 'boolean'],
        ];

        foreach (array_keys(RequisitionTemplate::CHECKLIST) as $key) {
            $rules["checklist.{$key}"] = ['nullable', 'array', 'max:'.RequisitionTemplate::MAX_LINES];
            $rules["checklist.{$key}.*"] = $line;
        }

        return $rules;
    }

    /**
     * The department must be one this person may ask for, resolved through the company scope; the Cabang and the
     * superior, when given, must belong to that department's company; and the start date must not be in the past.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->checkStartDate($validator);

                if ($validator->errors()->has('department_id')) {
                    return;
                }

                $department = $this->department();

                if ($department === null || ! app(RequisitionDepartments::class)->allows($this->user(), $department->id)) {
                    $validator->errors()->add('department_id', 'Anda tidak dapat mengajukan lowongan untuk departemen ini.');

                    return;
                }

                $branch = $this->integer('branch_id');

                if ($branch !== 0 && ! Branch::query()->whereKey($branch)->where('company_id', $department->company_id)->exists()) {
                    $validator->errors()->add('branch_id', 'Cabang tidak ditemukan di perusahaan ini.');
                }

                $manager = $this->integer('manager_employee_id');

                if ($manager !== 0 && ! Employee::acrossCompanies()->whereKey($manager)->where('company_id', $department->company_id)
                    ->where('employment_status', EmploymentStatus::Active)->exists()) {
                    $validator->errors()->add('manager_employee_id', 'Atasan langsung harus karyawan aktif di perusahaan ini.');
                }
            },
        ];
    }

    /**
     * Today in the company's timezone, which is where "in the past" is judged: a server clock in UTC would call
     * the early morning of today yesterday.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('attendance.device_timezone'))->startOfDay();
    }

    /**
     * The start date may not be before today, nor years away (REC-WZ1-004). A requisition already sent keeps a date
     * that has since passed as long as it is left alone: only a date being set or changed is judged, so correcting
     * something else on it is not blocked. A draft is judged every time it is saved, so a stale date is caught
     * before it is sent.
     */
    private function checkStartDate(Validator $validator): void
    {
        $date = $this->input('target_start_on');

        if (! is_string($date) || $date === '' || $validator->errors()->has('target_start_on')) {
            return;
        }

        $existing = $this->route('requisition');

        if ($existing instanceof Requisition && ! $existing->isDraft() && $existing->target_start_on?->toDateString() === $date) {
            return;
        }

        $today = self::today();

        if ($date < $today->toDateString()) {
            $validator->errors()->add('target_start_on', 'Target mulai kerja tidak boleh di masa lalu.');
        } elseif ($date > $today->addYears(self::MAX_YEARS_AHEAD)->toDateString()) {
            $validator->errors()->add('target_start_on', 'Target mulai kerja terlalu jauh ke depan (maksimal '.self::MAX_YEARS_AHEAD.' tahun).');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'Pilih departemen.',
            'position_name.required' => 'Isi nama posisi.',
            'quota.integer' => 'Jumlah kuota harus bilangan bulat.',
            'quota.min' => 'Jumlah kuota minimal 1.',
            'contract_months.integer' => 'Lama kontrak harus bilangan bulat (bulan).',
            'contract_months.min' => 'Lama kontrak minimal 1 bulan.',
            'contract_months.max' => 'Lama kontrak maksimal 60 bulan.',
            'target_start_on.date_format' => 'Target mulai kerja harus berupa tanggal yang sah.',
            'responsibilities.max' => 'Tanggung jawab utama maksimal '.self::MAX_SUBTITLES.' subtitle.',
            'responsibilities.*.title.max' => 'Satu subtitle maksimal '.self::MAX_TITLE_LENGTH.' karakter.',
            'responsibilities.*.items.*.max' => 'Satu baris maksimal '.RequisitionTemplate::MAX_LINE_LENGTH.' karakter.',
            'benefits.*.max' => 'Satu baris maksimal '.RequisitionTemplate::MAX_LINE_LENGTH.' karakter.',
            'checklist.*.*.max' => 'Satu baris maksimal '.RequisitionTemplate::MAX_LINE_LENGTH.' karakter.',
        ];
    }

    public function department(): ?Department
    {
        return Department::query()->find($this->integer('department_id'));
    }

    /**
     * The requisition's own fields, as the draft stores them. The contract length is kept only for the types that
     * have one.
     *
     * @return array<string, mixed>
     */
    public function requisitionFields(): array
    {
        $department = $this->department();
        $type = EmploymentType::tryFrom((string) $this->input('employment_type'));
        $checklist = [];

        foreach (array_keys(RequisitionTemplate::CHECKLIST) as $key) {
            $checklist[$key] = RequisitionTemplate::clean($this->input("checklist.{$key}", []));
        }

        return [
            'company_id' => $department?->company_id,
            'department_id' => $department?->id,
            'branch_id' => $this->integer('branch_id') ?: null,
            'position_name' => trim((string) $this->input('position_name')),
            'quota' => $this->filled('quota') ? $this->integer('quota') : null,
            'employment_type' => $type,
            'contract_months' => $type !== null && Requisition::typeNeedsMonths($type) && $this->filled('contract_months') ? $this->integer('contract_months') : null,
            'manager_employee_id' => $this->integer('manager_employee_id') ?: null,
            'reason' => $this->input('reason'),
            'target_start_on' => $this->input('target_start_on') ?: null,
            'about' => $this->input('about'),
            'responsibilities' => $this->input('responsibilities', []),
            'benefits' => $this->input('benefits', []),
            'checklist' => $checklist,
            'draft_step' => $this->integer('draft_step', 1),
        ];
    }
}
