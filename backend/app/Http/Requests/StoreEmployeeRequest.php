<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDossier;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeDossier;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseDossier();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->dossierRules();
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return $this->dossierChecks();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->dossierMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->dossierAttributes();
    }
}
