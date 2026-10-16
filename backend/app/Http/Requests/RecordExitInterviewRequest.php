<?php

namespace App\Http\Requests;

use App\Models\OffboardingCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The exit interview, or the reason it was skipped (OFF-SIG-003).
 */
class RecordExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OffboardingCase $case */
        $case = $this->route('offboardingCase');

        return $this->user()?->can('recordExitInterview', $case) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skip' => ['nullable', 'boolean'],
            'skipped_reason' => ['nullable', 'required_if_accepted:skip', 'string', 'max:1000'],
            'reason' => ['nullable', 'required_unless:skip,1', 'string', 'max:2000'],
            'rehire' => ['nullable', 'required_unless:skip,1', 'boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skipped_reason.required_if_accepted' => 'Tulis alasan wawancara dilewati.',
            'reason.required_unless' => 'Isi alasan utama.',
            'rehire.required_unless' => 'Pilih apakah bersedia direkrut kembali.',
        ];
    }
}
