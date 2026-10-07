<?php

namespace App\Http\Requests;

use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActiveCompanyRequest extends FormRequest
{
    /**
     * Only a parent-wide account may change the active company. A pinned account's switcher
     * is locked, and the lock is enforced here rather than only in the UI (CORE-NAV-004).
     */
    public function authorize(): bool
    {
        return $this->user()?->isParentWide() === true;
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
