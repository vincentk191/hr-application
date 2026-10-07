<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeDocumentRequest extends FormRequest
{
    use ValidatesEmployeeDocument;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->metadataRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->documentMessages();
    }
}
