<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDossier;
use App\Models\CandidateOffer;
use App\Models\Requisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Making the employee profile of a hire (REC-FIL-006/007): the fields of any new employee, checked by the same rules, in the
 * requisition's company whatever company the person is viewing. Who may is the policy's call, and only for an accepted offer
 * that has no profile yet.
 */
class ConvertHireRequest extends FormRequest
{
    use ValidatesEmployeeDossier;

    public function authorize(): bool
    {
        $requisition = $this->route('requisition');
        $offer = $this->route('offer');

        return $requisition instanceof Requisition
            && $offer instanceof CandidateOffer
            && $this->user()?->can('convertHire', [$requisition, $offer]) === true;
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

    /**
     * The profile belongs to the requisition's company, so the Cabang, department, position and manager are checked against
     * it, and no company is asked for.
     */
    protected function activeCompanyId(): ?int
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition ? $requisition->company_id : null;
    }
}
