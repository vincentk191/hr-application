<?php

namespace App\Http\Requests;

use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Support\Access\SupervisorReach;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActiveCompanyRequest extends FormRequest
{
    /**
     * A parent-wide account may change the active company, and so may a Supervisor whose team reaches
     * more than one. Any other pinned account's switcher is locked, and the lock is enforced here rather
     * than only in the UI (CORE-NAV-004).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->isParentWide() || app(SupervisorReach::class)->spansCompanies($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => [
                'required',
                Rule::anyOf([
                    [Rule::in([SetActiveCompany::COMBINED])],
                    ['integer', Rule::exists(Company::class, 'id')->where('is_active', true)],
                ]),
                // A pinned account may only pick a company its team reaches.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $user = $this->user();

                    if ($user === null || $user->isParentWide() || $value === SetActiveCompany::COMBINED) {
                        return;
                    }

                    if (! app(SupervisorReach::class)->covers($user, (int) $value)) {
                        $fail('Perusahaan ini tidak termasuk jangkauan Anda.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company.required' => 'Pilih perusahaan yang ingin dilihat.',
        ];
    }

    /**
     * The validated selection: a company id, or the combined-view sentinel.
     */
    public function selection(): string
    {
        return (string) $this->validated()['company'];
    }
}
