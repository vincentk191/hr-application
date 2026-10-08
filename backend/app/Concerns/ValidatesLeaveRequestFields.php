<?php

namespace App\Concerns;

use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Leave\LeaveLimits;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The fields of a leave request, shared by filing one and by correcting a rejected one, so the two
 * forms cannot drift: the same file rules and the same maximum days per leave type apply to both.
 */
trait ValidatesLeaveRequestFields
{
    public const MAX_ATTACHMENTS = 5;

    /**
     * @return array<string, mixed>
     */
    protected function leaveFieldRules(): array
    {
        return [
            'leave_type' => ['required', Rule::enum(LeaveType::class)],
            'start_on' => ['required', 'date'],
            'end_on' => ['required', 'date', 'after_or_equal:start_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
            // Supporting documents are optional: a sick note, an invitation. Images and PDFs only.
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function leaveFieldMessages(): array
    {
        return [
            'leave_type.required' => 'Pilih jenis cuti.',
            'start_on.required' => 'Isi hari pertama.',
            'end_on.required' => 'Isi hari terakhir.',
            'end_on.after_or_equal' => 'Hari terakhir tidak boleh sebelum hari pertama.',
            'attachments.max' => 'Lampiran maksimal '.self::MAX_ATTACHMENTS.' berkas.',
            'attachments.*.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'attachments.*.max' => 'Setiap lampiran maksimal 5 MB.',
            'attachments.*.file' => 'Lampiran tidak dapat dibaca.',
        ];
    }

    /**
     * The employee the request is for, so its days can be counted for their company and department.
     */
    abstract protected function leaveEmployee(): ?Employee;

    /**
     * Refuse a request that covers no working day, and one longer than HR allows for its type. The
     * days are counted the way the request counts them (working days), so what is checked here is
     * what would be stored.
     */
    protected function checkLeaveLimit(Validator $validator): void
    {
        foreach (['leave_type', 'start_on', 'end_on'] as $field) {
            if ($validator->errors()->has($field)) {
                return;
            }
        }

        $type = LeaveType::tryFrom((string) $this->input('leave_type'));
        $employee = $this->leaveEmployee();

        if ($type === null || $employee === null) {
            return;
        }

        $days = LeaveRequest::workingDays(
            CarbonImmutable::parse((string) $this->input('start_on')),
            CarbonImmutable::parse((string) $this->input('end_on')),
            $employee,
        );

        if ($days === 0) {
            $validator->errors()->add('end_on', 'Tanggal yang dipilih tidak mencakup hari kerja (akhir pekan atau hari libur).');

            return;
        }

        $max = app(LeaveLimits::class)->maxDays($type);

        if ($max !== null && $days > $max) {
            $validator->errors()->add(
                'end_on',
                "{$type->label()} maksimal {$max} hari kerja per pengajuan; pengajuan ini {$days} hari kerja.",
            );
        }
    }
}
