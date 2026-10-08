<?php

namespace App\Support\Leave;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Filing and removing the files on a leave request. They go to the private disk, grouped by
 * company and request, under a random name: the name the person gave is only kept for display.
 */
class LeaveAttachments
{
    public const DISK = 'local';

    /**
     * @param  list<UploadedFile>  $files
     */
    public function store(LeaveRequest $leave, array $files, User $by): void
    {
        foreach ($files as $file) {
            $leave->attachments()->create([
                'company_id' => $leave->company_id,
                'path' => $file->store("leave-attachments/{$leave->company_id}/{$leave->id}", self::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by_user_id' => $by->id,
            ]);
        }
    }

    /**
     * Remove some of a request's own files, by id. An id that belongs to another request is
     * ignored rather than trusted.
     *
     * @param  array<int, int>  $ids
     */
    public function remove(LeaveRequest $leave, array $ids): void
    {
        $leave->attachments()->whereIn('id', $ids)->get()->each(function (LeaveRequestAttachment $attachment): void {
            Storage::disk(self::DISK)->delete($attachment->path);
            $attachment->delete();
        });
    }
}
