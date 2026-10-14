<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesLeaveRequestFields;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    use ValidatesLeaveRequestFields;

    private ?Employee $resolvedEmployee = null;

    private bool $employeeResolved = false;

    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveRequest::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Deliberately no `exists` rule: it queries the raw table and so ignores
            // CompanyScope, which would let an employee from another company pass validation
            // and then fail at the lookup. The scoped resolution in after() is the only check.
            'employee_id' => ['required', 'integer'],
            ...$this->leaveFieldRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->checkLeaveLimit($validator);
                $this->checkBackdating($validator);

                if ($validator->errors()->has('employee_id')) {
                    return;
                }

                $employee = $this->employee();

                // An employee in another company is indistinguishable from one that does not
                // exist, which is exactly how tenancy should look from the outside.
                if ($employee === null) {
                    $validator->errors()->add('employee_id', 'Karyawan tidak ditemukan.');

                    return;
                }

                // Filing for someone else is for HR Admin (anyone in the company) and Management
                // (their downline) only (REQ-DSH-005). Filing for yourself needs no extra permission.
                if ($this->user()?->cannot('fileFor', [LeaveRequest::class, $employee])) {
                    $validator->errors()->add(
                        'employee_id',
                        'Anda tidak dapat mengajukan pengajuan untuk karyawan ini.',
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
        return [
            ...$this->leaveFieldMessages(),
            'employee_id.required' => 'Pilih karyawan.',
        ];
    }

    protected function leaveEmployee(): ?Employee
    {
        return $this->employee();
    }

    /**
     * The employee this request is for, resolved through the company scope and memoised so
     * validation and the controller cannot disagree about who it is.
     */
    public function employee(): ?Employee
    {
        if (! $this->employeeResolved) {
            $this->resolvedEmployee = Employee::find($this->integer('employee_id'));
            $this->employeeResolved = true;
        }

        return $this->resolvedEmployee;
    }
}
