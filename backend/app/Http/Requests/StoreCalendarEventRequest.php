<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Department;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add a holiday or company event (ATT-WIZ-001).
 *
 * Who may add what is settled here, not on the form: a company-pinned HR admin can only add events
 * for their own company and its departments, whatever the form says. Only a parent-wide account
 * can add a group-wide holiday or choose another company.
 */
class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageCalendarEvents->value) === true;
    }

    /**
     * Pin a company-bound user's event to their own company before validating.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user !== null && ! $user->isParentWide()) {
            $this->merge(['company_id' => $user->company_id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([CalendarEvent::HOLIDAY, CalendarEvent::EVENT])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            // Empty means the whole group; only a parent-wide user's request can leave it empty.
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'department_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $data = $validator->getData();

                // The rules above have already flagged a malformed date; do not parse it again.
                if ($validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                    return;
                }

                if (! empty($data['starts_on']) && ! empty($data['ends_on'])
                    && CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) >= CalendarEvent::MAX_DAYS) {
                    $validator->errors()->add('ends_on', 'Satu acara paling lama '.CalendarEvent::MAX_DAYS.' hari.');
                }

                if (! empty($data['department_id'])) {
                    if (empty($data['company_id'])) {
                        $validator->errors()->add('department_id', 'Pilih perusahaan untuk departemen ini.');

                        return;
                    }

                    $belongs = Department::acrossCompanies()
                        ->whereKey($data['department_id'])
                        ->where('company_id', $data['company_id'])
                        ->exists();

                    if (! $belongs) {
                        $validator->errors()->add('department_id', 'Departemen tidak ditemukan di perusahaan itu.');
                    }
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
            'title.required' => 'Isi nama hari libur atau acara.',
            'kind.required' => 'Pilih jenis.',
            'starts_on.required' => 'Isi tanggal mulai.',
            'ends_on.required' => 'Isi tanggal selesai.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }
}
