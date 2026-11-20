<?php

namespace App\Models;

use App\Enums\TaxMethod;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One of the parent group's operating companies.
 *
 * This model is deliberately not company-tagged: it is the thing being tagged. Which
 * companies a given user may see is decided by the switcher and by CORE-USR-004, not by a
 * global scope on this table.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $brand_color
 * @property string|null $logo_path
 * @property string|null $logo_dark_path
 * @property string|null $npwp_15
 * @property string|null $npwp_16
 * @property string|null $npwp_22
 * @property TaxMethod|null $tax_method how it pays tax on pay, printed on the employee induction form
 * @property string|null $google_sender_email the one Google account interview invitations may be sent from
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'brand_color', 'logo_path', 'logo_dark_path', 'address', 'npwp_15', 'npwp_16', 'npwp_22', 'tax_method', 'google_sender_email', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_method' => TaxMethod::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Where the logo is served from, or null when there is none. Logos sit on the public disk, like
     * profile pictures: they are shown on every page and are not private.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    /**
     * The logo for dark mode, or null when only one was uploaded (the light logo is then used in both modes).
     */
    public function logoDarkUrl(): ?string
    {
        return $this->logo_dark_path === null ? null : Storage::disk('public')->url($this->logo_dark_path);
    }

    /**
     * The Google account connected for this company's interview invitations, if any.
     *
     * @return HasOne<GoogleConnection, $this>
     */
    public function googleConnection(): HasOne
    {
        return $this->hasOne(GoogleConnection::class)->withoutGlobalScopes();
    }

    /**
     * The mailbox this company's emails are sent from, if an admin has set one.
     *
     * @return HasOne<CompanyMailSetting, $this>
     */
    public function mailSetting(): HasOne
    {
        return $this->hasOne(CompanyMailSetting::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Accounts pinned to this company. A parent-wide account has no company.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
