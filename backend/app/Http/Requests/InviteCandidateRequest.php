<?php

namespace App\Http\Requests;

use App\Models\Requisition;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Inviting a shortlisted candidate to interview (REC-INT): when (in the company's time, a future time within a year),
 * online or in person, and where: a Meet link or an address.
 */
class InviteCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition && $this->user()?->can('manageInterview', $requisition) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interview_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'interview_mode' => ['required', 'in:online,onsite'],
            'interview_place' => ['required', 'string', 'max:300'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interview_at.required' => 'Pilih tanggal dan jam interview.',
            'interview_at.date_format' => 'Tanggal dan jam interview tidak valid.',
            'interview_mode.required' => 'Pilih online atau tatap muka.',
            'interview_mode.in' => 'Pilih online atau tatap muka.',
            'interview_place.required' => 'Isi tautan Meet atau alamat tempat interview.',
            'interview_place.max' => 'Tautan atau alamat terlalu panjang (maksimal 300 huruf).',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('interview_at')) {
                    return;
                }

                $when = CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('interview_at'), (string) config('attendance.device_timezone'));

                if (! $when instanceof CarbonImmutable || $when->isPast()) {
                    $validator->errors()->add('interview_at', 'Waktu interview harus di masa depan.');
                } elseif ($when->greaterThan(now()->addYear())) {
                    $validator->errors()->add('interview_at', 'Waktu interview terlalu jauh ke depan (maksimal 1 tahun).');
                }
            },
        ];
    }

    /**
     * @return array{interview_at: string, interview_mode: string, interview_place: string, note: string|null}
     */
    public function invitationFields(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'interview_at' => (string) $validated['interview_at'],
            'interview_mode' => (string) $validated['interview_mode'],
            'interview_place' => (string) $validated['interview_place'],
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
        ];
    }
}
