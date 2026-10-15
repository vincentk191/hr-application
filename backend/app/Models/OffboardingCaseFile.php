<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\OffboardingFileKind;
use Database\Factories\OffboardingCaseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file on an offboarding case (OFF-PIP-006). Served only through the case's policy.
 *
 * @property int $id
 * @property int $company_id
 * @property int $offboarding_case_id
 * @property OffboardingFileKind $kind
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['company_id', 'offboarding_case_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by_user_id'])]
class OffboardingCaseFile extends Model
{
    /** @use HasFactory<OffboardingCaseFileFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => OffboardingFileKind::class, 'size_bytes' => 'integer'];
    }

    /**
     * @return BelongsTo<OffboardingCase, $this>
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }
}
