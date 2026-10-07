<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDossier;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeDossier;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->dossierRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->dossierMessages();
    }
}
