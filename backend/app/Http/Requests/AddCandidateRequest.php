<?php

namespace App\Http\Requests;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shortlisting a candidate (REC-SCR): a name, at least a phone number or an email to reach them by, where they came
 * from, and optionally a note and the CV (PDF, picture or Word, up to 5 MB).
 */
class AddCandidateRequest extends FormRequest
{
    public const CV_MAX_KB = 5120;

    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition && $this->user()?->can('manageScreening', $requisition) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:160'],
            'source' => ['required', Rule::enum(CandidateSource::class)],
            'source_name' => ['nullable', 'string', 'max:60', 'required_if:source,'.CandidateSource::Other->value],
            'note' => ['nullable', 'string', 'max:1000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:'.self::CV_MAX_KB],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama kandidat.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, - dan tanda kurung.',
            'email.email' => 'Format email tidak valid.',
            'source.required' => 'Pilih asal kandidat.',
            'source.enum' => 'Asal kandidat tidak dikenal.',
            'source_name.required_if' => 'Tulis nama sumbernya.',
            'cv.mimes' => 'CV harus berupa PDF, gambar (JPG, PNG, WebP) atau Word.',
            'cv.max' => 'CV terlalu besar (maksimal 5 MB).',
        ];
    }

    /**
     * A candidate has to be reachable: a usable phone number or an email.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['phone', 'email'])) {
                    return;
                }

                $hasPhone = Candidate::phoneKey($this->input('phone')) !== null;

                if (! $hasPhone && ! filled($this->input('email'))) {
                    $validator->errors()->add('phone', filled($this->input('phone'))
                        ? 'Nomor telepon tidak valid. Periksa jumlah angkanya, atau isi email.'
                        : 'Isi nomor telepon atau email supaya kandidat bisa dihubungi.');
                }
            },
        ];
    }

    /**
     * @return array{name: string, phone: string|null, email: string|null, source: string, source_name: string|null, note: string|null}
     */
    public function candidateFields(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
            'email' => isset($validated['email']) ? (string) $validated['email'] : null,
            'source' => (string) $validated['source'],
            'source_name' => filled($validated['source_name'] ?? null) ? trim((string) $validated['source_name']) : null,
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
        ];
    }
}
