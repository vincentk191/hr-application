<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeDocumentVersionRequest extends FormRequest
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
        return [
            'file' => ['required', ...$this->fileRules()],
            'note' => ['nullable', 'string', 'max:255'],
            // A renewed document usually carries a new date; leaving it out keeps the old one.
            'expires_on' => ['nullable', 'date'],
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
