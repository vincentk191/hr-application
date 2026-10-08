<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file attached to a leave request. Kept on the private disk; see LeaveAttachmentController.
 *
 * @property int $id
 * @property int $company_id
 * @property int $leave_request_id
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['company_id', 'leave_request_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by_user_id'])]
class LeaveRequestAttachment extends Model
{
    use BelongsToCompany;

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
