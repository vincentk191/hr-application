<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Enums\ProfileChangeStatus;
use Database\Factories\ProfileChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee's proposed change to their own profile, which takes effect only when HR approves it
 * (EMP-CHG).
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $requested_by_user_id
 * @property ProfileChangeStatus $status
 * @property array<string, array{before: string|null, after: string|null}> $changes
 * @property list<string>|null $conflicts
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'employee_id',
    'requested_by_user_id',
    'status',
    'changes',
    'conflicts',
    'decided_by_user_id',
    'decided_at',
    'decision_note',
])]
class ProfileChangeRequest extends Model
{
    /** @use HasFactory<ProfileChangeRequestFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * The fields an employee may ask to change themselves (EMP-CHG-001). Everything else on the
     * profile (employment, pay, health, NIK) is HR's alone. This list is expected to grow: add a
     * field here and the form, the review and the approval pick it up.
     *
     * `type` is how the form draws it; `sensitive` fields are hidden from reviewers who may not see
     * bank and tax data.
     *
     * @var array<string, array{label: string, group: string, type: 'text'|'email'|'textarea'|'photo', sensitive: bool}>
     */
    public const FIELDS = [
        'phone' => ['label' => 'Telepon', 'group' => 'Kontak', 'type' => 'text', 'sensitive' => false],
        'email' => ['label' => 'Email', 'group' => 'Kontak', 'type' => 'email', 'sensitive' => false],
        'address' => ['label' => 'Alamat', 'group' => 'Kontak', 'type' => 'textarea', 'sensitive' => false],
        'emergency_contact_name' => ['label' => 'Nama kontak darurat', 'group' => 'Kontak darurat', 'type' => 'text', 'sensitive' => false],
        'emergency_contact_phone' => ['label' => 'Telepon kontak darurat', 'group' => 'Kontak darurat', 'type' => 'text', 'sensitive' => false],
        'emergency_contact_relation' => ['label' => 'Hubungan', 'group' => 'Kontak darurat', 'type' => 'text', 'sensitive' => false],
        'bank_name' => ['label' => 'Bank', 'group' => 'Bank & pajak', 'type' => 'text', 'sensitive' => true],
        'bank_account_number' => ['label' => 'Nomor rekening', 'group' => 'Bank & pajak', 'type' => 'text', 'sensitive' => true],
        'bank_account_holder' => ['label' => 'Nama pemilik rekening', 'group' => 'Bank & pajak', 'type' => 'text', 'sensitive' => true],
        'tax_number' => ['label' => 'NPWP', 'group' => 'Bank & pajak', 'type' => 'text', 'sensitive' => true],
        'photo_path' => ['label' => 'Foto profil', 'group' => 'Foto', 'type' => 'photo', 'sensitive' => false],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileChangeStatus::class,
            'changes' => 'array',
            'conflicts' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === ProfileChangeStatus::Pending;
    }

    public function reference(): string
    {
        return 'PRF-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ProfileChangeStatus::Pending->value);
    }
}
