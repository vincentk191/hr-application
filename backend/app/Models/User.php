<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Support\Access\Entrance;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property bool $awaiting_activation
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'company_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    use HasRoles {
        checkPermissionTo as private permissionCheckedByRoles;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'awaiting_activation' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A deactivated account loses access at once, not at its next login attempt.
        static::saved(function (self $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->revokeAccess();
            }
        });
    }

    /**
     * Sign this account out everywhere and cancel its tokens (CORE-AUT-006): every browser session,
     * the remember-me cookie, and any API token.
     *
     * Sessions are deleted from the sessions table, so this needs the database session driver — the
     * one this application is configured for. With another driver it would silently do less.
     *
     * @param  string|null  $exceptSessionId  a session to keep, for someone changing their own password
     */
    public function revokeAccess(?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($exceptSessionId !== null, fn ($sessions) => $sessions->where('id', '!=', $exceptSessionId))
            ->delete();

        $this->tokens()->delete();
        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /**
     * The company this account is pinned to, or null for a parent-wide account.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The dossier record behind this login, if the account was provisioned from one.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Whether this account may switch between companies and view them combined.
     *
     * A parent-wide account (group HR, group Management) has no company of its own; every
     * other account is locked to one (CORE-USR-004, CORE-NAV-003/004).
     */
    public function isParentWide(): bool
    {
        return $this->company_id === null;
    }

    /**
     * Whether this account may look at the given company's data.
     */
    public function canAccessCompany(Company|int $company): bool
    {
        if ($this->isParentWide()) {
            return true;
        }

        return $this->company_id === ($company instanceof Company ? $company->id : $company);
    }

    /**
     * Limit a query to the accounts that may look at the given company's data: its own accounts and
     * every parent-wide one. The same rule as canAccessCompany(), for when the people are chosen in
     * the database rather than filtered afterwards.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function accessingCompany(Builder $query, Company|int $company): void
    {
        $query->where(function (Builder $query) use ($company): void {
            $query->whereNull('company_id')
                ->orWhere('company_id', $company instanceof Company ? $company->id : $company);
        });
    }

    /**
     * Who may open the admin panel: a parent-wide account holding the access permission.
     *
     * The permission is what is assignable to roles; the parent-wide requirement is a fixed
     * rule, because the panel administers every company (CORE-NAV-003).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdminPanel();
    }

    /**
     * The rule behind canAccessPanel(), without needing a Filament panel to ask it of — the
     * sidebar asks on every page, and Filament is not loaded on pages outside the panel.
     */
    public function canAccessAdminPanel(): bool
    {
        return $this->isParentWide()
            // checkPermissionTo, not hasPermissionTo: it must answer false rather than throw
            // while the permission does not exist yet.
            && $this->checkPermissionTo(Permission::AccessAdminPanel->value);
    }

    /**
     * Whether this account holds the given role.
     *
     * Wraps spatie's string-based check so callers pass the Role enum: a typo then becomes a
     * type error rather than a permission check that silently returns false.
     */
    public function isA(Role $role): bool
    {
        // On the public entrance everyone is a plain employee (CORE-ACC-004).
        if ($role !== Role::Employee && app(Entrance::class)->isPublic()) {
            return false;
        }

        return $this->hasRole($role->value);
    }

    /**
     * Every permission check goes through here (spatie's gate hook calls it), so on the public entrance an
     * account holds none: it sees and can do only what any employee can (CORE-ACC-004).
     *
     * @param  int|string|\BackedEnum|\Spatie\Permission\Contracts\Permission  $permission
     */
    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        if (app(Entrance::class)->isPublic()) {
            return false;
        }

        return $this->permissionCheckedByRoles($permission, $guardName);
    }
}
