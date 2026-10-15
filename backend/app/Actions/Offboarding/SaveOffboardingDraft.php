<?php

namespace App\Actions\Offboarding;

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\Offboarding\OffboardingFiles;
use App\Workflows\Definitions\OffboardingCaseWorkflow;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * Saves a case as a draft, new or changed (OFF-WIZ-002). A draft can be half-filled and finished
 * later; submitting is what checks it is complete.
 */
class SaveOffboardingDraft
{
    public function __construct(
        private WorkflowEngine $engine,
        private OffboardingCaseWorkflow $definition,
        private OffboardingFiles $files,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the case's own fields
     * @param  list<array{kind: \App\Enums\OffboardingFileKind, files: list<\Illuminate\Http\UploadedFile>}>  $uploads
     * @param  array<int, int>  $removeFileIds
     */
    public function handle(?OffboardingCase $case, ?Employee $employee, array $data, array $uploads, array $removeFileIds, User $by): OffboardingCase
    {
        return DB::transaction(function () use ($case, $employee, $data, $uploads, $removeFileIds, $by): OffboardingCase {
            if ($case === null) {
                $case = OffboardingCase::create([
                    ...$data,
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'created_by_user_id' => $by->id,
                ]);

                $this->engine->start($case, $this->definition, [], $by);
            } else {
                $case->update($data);
            }

            $this->files->remove($case, $removeFileIds);

            foreach ($uploads as $upload) {
                $this->files->store($case, $upload['kind'], $upload['files'], $by);
            }

            return $case;
        });
    }
}
