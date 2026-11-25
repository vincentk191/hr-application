<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevealEmployeeFieldRequest;
use App\Models\Employee;
use App\Support\Employees\SensitiveFields;
use Illuminate\Http\JsonResponse;

/**
 * Shows one masked profile field in full, and writes down that it was looked at. The profile itself only carries the
 * masks (see SensitiveFields), so this is the one way the real value leaves the server for a read.
 */
class EmployeeFieldRevealController extends Controller
{
    public function store(RevealEmployeeFieldRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $field = $request->validated('field');

        // The same rule the profile applies to whole cards: bank, payroll and the like need more than seeing the record.
        if (SensitiveFields::isRestricted($field)) {
            $this->authorize('viewSensitive', $employee);
        }

        activity('employee')
            ->performedOn($employee)
            ->causedBy($request->user())
            ->withProperties(['field' => $field])
            ->log('Sensitive field revealed');

        return response()->json(['value' => $employee->getAttribute($field)]);
    }
}
