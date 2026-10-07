<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One uploaded file of an employee document. Not company-tagged itself: it is only ever reached
 * through its document, which is.
 *
 * @property int $id
 * @property int $employee_document_id
 * @property int $version_no
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by_user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'employee_document_id',
    'version_no',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'uploaded_by_user_id',
    'note',
])]
class EmployeeDocumentVersion extends Model
{
    /**
     * @return BelongsTo<EmployeeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
