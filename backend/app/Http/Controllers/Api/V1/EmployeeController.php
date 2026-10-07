<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    /**
     * Company-scoped by CompanyScope exactly like the web directory; there is no company clause
     * here to forget.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection(
            Employee::query()->with(['company:id,code,name', 'department:id,name', 'position:id,name', 'manager:id,full_name'])
                ->orderBy('full_name')
                ->get(),
        );
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee->load(['company:id,code,name', 'department:id,name', 'position:id,name', 'manager:id,full_name']));
    }
}
