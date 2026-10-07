<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesEmployeeDossier;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeDossier;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('employee')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->dossierRules(ignoring: $this->route('employee'));
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = $this->route('employee');

                // A reporting line that points at itself is a loop the downline resolver would
                // walk forever, so it is refused at the edge rather than guarded deeper in.
                if ($employee instanceof Employee
                    && $this->integer('manager_id') === $employee->id) {
                    $validator->errors()->add(
                        'manager_id',
                        'Karyawan tidak bisa menjadi atasan dirinya sendiri.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->dossierMessages();
    }
}
