<?php

namespace App\Support\Offboarding;

use App\Enums\OffboardingFileKind;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseFile;
use App\Models\User;
use App\Support\Files\UploadFiler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Filing and removing the files on an offboarding case. They go to the private disk under a random
 * name, grouped by company and case; the name the person gave is only kept for display.
 */
class OffboardingFiles
{
    public const DISK = 'local';

    public function __construct(private readonly UploadFiler $filer) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function store(OffboardingCase $case, OffboardingFileKind $kind, array $files, User $by): void
    {
        // There is one resignation letter: a new one replaces the old.
        if ($kind === OffboardingFileKind::ResignationLetter && $files !== []) {
            $this->remove($case, $case->files()->where('kind', $kind->value)->pluck('id')->all());
        }

        foreach ($files as $file) {
            $stored = $this->filer->store($file, "offboarding-files/{$case->company_id}/{$case->id}", self::DISK);

            $case->files()->create([
                'company_id' => $case->company_id,
                'kind' => $kind,
                'path' => $stored['path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $stored['size'],
                'uploaded_by_user_id' => $by->id,
            ]);
        }
    }

    /**
     * Remove some of a case's own files, by id. An id that belongs to another case is ignored.
     *
     * @param  array<int, int>  $ids
     */
    public function remove(OffboardingCase $case, array $ids): void
    {
        $case->files()->whereIn('id', $ids)->get()->each(function (OffboardingCaseFile $file): void {
            Storage::disk(self::DISK)->delete($file->path);
            $file->delete();
        });
    }
}
