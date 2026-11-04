<?php

namespace App\Http\Requests;

use App\Enums\JobPlatform;
use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording where the listing was posted (REC-PST-002): the platform (a name for "Lainnya"), the date, and the link
 * if HR has it. The date cannot be in the future, judged in the company timezone like a requisition's start date.
 */
class SavePostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition && $this->user()?->can('managePostings', $requisition) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(JobPlatform::class)],
            'platform_name' => ['nullable', 'string', 'max:60', 'required_if:platform,'.JobPlatform::Other->value],
            'url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'posted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.SaveRequisitionRequest::today()->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.required' => 'Pilih platform.',
            'platform.enum' => 'Platform tidak dikenal.',
            'platform_name.required_if' => 'Tulis nama platformnya.',
            'platform_name.max' => 'Nama platform terlalu panjang (maksimal 60 huruf).',
            'url.url' => 'Tautan harus diawali http:// atau https://.',
            'url.max' => 'Tautan terlalu panjang.',
            'posted_on.required' => 'Isi tanggal posting.',
            'posted_on.date_format' => 'Tanggal posting tidak valid.',
            'posted_on.before_or_equal' => 'Tanggal posting tidak boleh setelah hari ini.',
        ];
    }

    /**
     * @return array{platform: string, platform_name: string|null, url: string|null, posted_on: string}
     */
    public function postingFields(): array
    {
        /** @var array{platform: string, platform_name?: string|null, url?: string|null, posted_on: string} $validated */
        $validated = $this->validated();

        return [
            'platform' => $validated['platform'],
            'platform_name' => filled($validated['platform_name'] ?? null) ? trim((string) $validated['platform_name']) : null,
            'url' => filled($validated['url'] ?? null) ? trim((string) $validated['url']) : null,
            'posted_on' => $validated['posted_on'],
        ];
    }
}
