<?php

namespace App\Http\Requests;

use App\Support\Employees\SensitiveFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevealEmployeeFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(SensitiveFields::names())],
        ];
    }
}
