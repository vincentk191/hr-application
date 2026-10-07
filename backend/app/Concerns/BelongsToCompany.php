<?php

namespace App\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as company-tagged: its rows belong to one operating company, every query is
 * filtered to the active company, and new rows inherit it (context.md §9.1, CORE-NAV-002).
 *
 * Applying this trait is the whole contract — do not also register CompanyScope by hand.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        // A record is created in the company the user is currently working in, so a
        // company-pinned user cannot plant a row in another company (EMP-ADD-004).
        static::creating(function (self $model): void {
            if ($model->getAttribute('company_id') !== null) {
                return;
            }

            $companyId = app(CompanyContext::class)->companyId();

            if ($companyId !== null) {
                $model->setAttribute('company_id', $companyId);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Query across every company, ignoring the active one.
     *
     * Bypassing tenancy is deliberate and rare: parent-wide reporting, and console or queue
     * work that legitimately spans companies. Authorise the caller before using it.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function acrossCompanies(Builder $query): void
    {
        $query->withoutGlobalScope(CompanyScope::class);
    }
}
