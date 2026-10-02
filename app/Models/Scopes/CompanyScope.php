<?php

namespace App\Models\Scopes;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters every query on a company-tagged model to the request's active company.
 *
 * A global scope is the right tool here for the reason the Eloquent guidance gives: this is
 * tenancy, and it has to hold for queries nobody remembered to constrain. The filter is
 * applied server-side, so a company-pinned user cannot reach another company's record by
 * typing its URL (CORE-NAV-004, CORE-RBA-003).
 *
 * When no company is active the scope adds nothing — see CompanyContext for why that is the
 * correct behaviour for console, queue and combined-view contexts rather than a hole.
 *
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(CompanyContext::class);

        if (! $context->isConstrained()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('company_id'),
            $context->companyId(),
        );
    }
}
