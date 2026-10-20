<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile pictures are not public files (CORE-ACC-008): they are served here, to someone who may see the record,
 * and only on the private entrance (the route is classed private).
 */
class EmployeePhotoController extends Controller
{
    public function show(Employee $employee): StreamedResponse
    {
        $this->authorize('view', $employee);

        return $this->stream($employee->photo_path);
    }

    /**
     * A picture waiting for HR's decision, before or after the change.
     */
    public function change(ProfileChangeRequest $profileChange, string $side): StreamedResponse
    {
        $this->authorize('view', $profileChange);
        abort_unless(in_array($side, ['before', 'after'], true), 404);

        return $this->stream($profileChange->changes['photo_path'][$side] ?? null);
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk(Employee::PHOTO_DISK)->exists($path), 404);

        return Storage::disk(Employee::PHOTO_DISK)->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
