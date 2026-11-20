<?php

namespace App\Jobs;

use App\Actions\Employees\CreateInductionForm;
use App\Models\Employee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Makes a new employee's induction form after the employee has been saved. Done on the queue so that adding someone is never
 * held up, or failed, by drawing a PDF; CreateInductionForm is safe to run again, so the queue may retry it.
 */
class CreateInductionFormJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $employeeId) {}

    public function handle(CreateInductionForm $create): void
    {
        $employee = Employee::acrossCompanies()->find($this->employeeId);

        if ($employee instanceof Employee) {
            $create->handle($employee);
        }
    }
}
