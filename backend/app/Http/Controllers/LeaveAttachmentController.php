<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequestAttachment;
use App\Support\Files\InlineImage;
use App\Support\Leave\LeaveAttachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the files on a leave request. They are on the private disk, so this is the only way to
 * reach one, and it asks the leave request's policy on every download.
 */
class LeaveAttachmentController extends Controller
{
    public function download(Request $request, LeaveRequestAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->leaveRequest);

        abort_unless(Storage::disk(LeaveAttachments::DISK)->exists($attachment->path), 404);

        return InlineImage::serve($request, LeaveAttachments::DISK, $attachment->path, $attachment->original_name);
    }
}
