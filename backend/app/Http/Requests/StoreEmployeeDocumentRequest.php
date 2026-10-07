<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDocument;
use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeDocumentRequest extends FormRequest
{
    use ValidatesEmployeeDocument;

    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeDocument::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->metadataRules(),
            'file' => ['required', ...$this->fileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->documentMessages();
    }
}
