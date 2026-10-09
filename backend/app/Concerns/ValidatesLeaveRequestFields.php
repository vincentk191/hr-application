<?php

namespace App\Concerns;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Support\Leave\LeaveLimits;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The fields of a request (pengajuan), shared by filing one and by correcting a rejected one, so the
 * two forms cannot drift: the same file rules, the same optional timeframe and the same maximum days
 * per type apply to both.
 */
trait ValidatesLeaveRequestFields
{
    public const MAX_ATTACHMENTS = 5;

    /**
     * @param  string|null  $keepType  a type the request already has, which stays allowed even if
     *                                 it has since been switched off for new requests
     * @return array<string, mixed>
     */
    protected function leaveFieldRules(?string $keepType = null): array
    {
        return [
            'leave_type' => ['required', Rule::exists(RequestType::class, 'code')->where(
                fn ($query) => $query->where('is_active', true)->when($keepType !== null, fn ($inner) => $inner->orWhere('code', $keepType)),
            )],
            'start_on' => ['required', 'date'],
            'end_on' => ['required', 'date', 'after_or_equal:start_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
            // The timeframe is optional: tick the box, then give the times. Unticked, the times are
            // ignored whatever was sent.
            'use_timeframe' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'required_if_accepted:use_timeframe', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if_accepted:use_timeframe', 'date_format:H:i', 'after:start_time'],
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
            'leave_type.required' => 'Pilih jenis pengajuan.',
            'leave_type.exists' => 'Jenis pengajuan ini tidak tersedia.',
            'start_on.required' => 'Isi hari pertama.',
            'end_on.required' => 'Isi hari terakhir.',
            'end_on.after_or_equal' => 'Hari terakhir tidak boleh sebelum hari pertama.',
            'start_time.required_if_accepted' => 'Isi jam mulai.',
            'end_time.required_if_accepted' => 'Isi jam selesai.',
            'start_time.date_format' => 'Jam harus berformat JJ:MM.',
            'end_time.date_format' => 'Jam harus berformat JJ:MM.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'attachments.max' => 'Lampiran maksimal '.self::MAX_ATTACHMENTS.' berkas.',
            'attachments.*.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'attachments.*.max' => 'Setiap lampiran maksimal 5 MB.',
            'attachments.*.file' => 'Lampiran tidak dapat dibaca.',
        ];
    }

    /**
     * The times to store: the ones sent when the timeframe box was ticked, nothing otherwise.
     *
     * @return array{start_time: string|null, end_time: string|null}
     */
    public function timeframe(): array
    {
        if (! $this->boolean('use_timeframe')) {
            return ['start_time' => null, 'end_time' => null];
        }

        return ['start_time' => (string) $this->input('start_time'), 'end_time' => (string) $this->input('end_time')];
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

        $type = RequestType::query()->where('code', (string) $this->input('leave_type'))->first();
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

        $max = app(LeaveLimits::class)->maxDays($type->code);

        if ($max !== null && $days > $max) {
            $validator->errors()->add(
                'end_on',
                "{$type->label} maksimal {$max} hari kerja per pengajuan; pengajuan ini {$days} hari kerja.",
            );
        }
    }
}
