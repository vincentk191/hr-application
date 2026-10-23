<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\Branch;
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
 * for their own company and its departments and Cabang, whatever the form says. Only a parent-wide account
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
            // Repeating (ATT-WIZ-002): how, and until when. Left blank, it happens once.
            'repeat' => ['nullable', Rule::in(array_keys(CalendarEvent::REPEATS))],
            'repeat_until' => ['nullable', 'required_with:repeat', 'date_format:Y-m-d', 'after:ends_on'],
            // Empty means the whole group; only a parent-wide user's request can leave it empty.
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'department_id' => ['nullable', 'integer'],
            // Empty means every Cabang of the company. Checked against the company in after().
            'branch_id' => ['nullable', 'integer'],
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

                $this->checkRepeat($validator, $data);

                $this->checkBelongsToCompany($validator, $data, 'department_id', Department::class, 'departemen');
                $this->checkBelongsToCompany($validator, $data, 'branch_id', Branch::class, 'cabang');
            },
        ];
    }

    /**
     * A department or Cabang can only narrow an event to a company, and must be that company's own.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<Branch|Department>  $model
     */
    private function checkBelongsToCompany(Validator $validator, array $data, string $field, string $model, string $label): void
    {
        if (empty($data[$field])) {
            return;
        }

        if (empty($data['company_id'])) {
            $validator->errors()->add($field, "Pilih perusahaan untuk {$label} ini.");

            return;
        }

        $belongs = $model::acrossCompanies()
            ->whereKey($data[$field])
            ->where('company_id', $data['company_id'])
            ->exists();

        if (! $belongs) {
            $validator->errors()->add($field, ucfirst($label).' tidak ditemukan di perusahaan itu.');
        }
    }

    /**
     * A repeat must leave room between occurrences and stay within the cap on how many are made.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkRepeat(Validator $validator, array $data): void
    {
        if (empty($data['repeat']) || empty($data['repeat_until']) || $validator->errors()->hasAny(['repeat', 'repeat_until'])) {
            return;
        }

        $starts = CarbonImmutable::parse($data['starts_on']);
        $ends = CarbonImmutable::parse($data['ends_on']);
        $days = (int) $starts->diffInDays($ends) + 1;
        $room = ['weekly' => 7, 'monthly' => 28, 'yearly' => 365][$data['repeat']];

        if ($days > $room) {
            $validator->errors()->add('repeat', 'Acara sepanjang '.$days.' hari tidak muat berulang '.strtolower(CalendarEvent::REPEATS[$data['repeat']]).'.');

            return;
        }

        $count = count(CalendarEvent::occurrences($starts, $ends, $data['repeat'], CarbonImmutable::parse($data['repeat_until'])));

        if ($count > CalendarEvent::MAX_OCCURRENCES) {
            $validator->errors()->add('repeat_until', 'Terlalu banyak pengulangan (maksimal '.CalendarEvent::MAX_OCCURRENCES.'). Pilih tanggal berakhir yang lebih dekat.');
        }
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
            'repeat.in' => 'Pilihan pengulangan tidak dikenal.',
            'repeat_until.required_with' => 'Isi sampai kapan berulang.',
            'repeat_until.after' => 'Tanggal berakhir pengulangan harus setelah tanggal selesai.',
        ];
    }
}
