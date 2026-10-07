<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Container\Attributes\Scoped;

/**
 * The company a request is currently looking at.
 *
 * Resolved once per request by the SetActiveCompany middleware and read by CompanyScope,
 * which is what keeps one company's data out of another's (context.md §9.1, CORE-NAV-004).
 *
 * Three states matter, and they are not interchangeable:
 *
 * - **Constrained** — `$companyId` is set, so every company-tagged query is filtered to it.
 *   This is what a company-pinned user always gets, and what a parent-wide user gets while
 *   they have one company selected.
 * - **Combined** — a parent-wide role is deliberately viewing all companies at once, so no
 *   filter is applied (CORE-NAV-003).
 * - **Unresolved** — there is no request context at all: console commands, queued jobs and
 *   tests. Nothing is filtered, so code running here must scope its own queries.
 *
 * Only the first state filters. `isCombined()` exists so callers can tell a deliberate
 * all-companies view apart from "no context was ever set", which look identical to the scope
 * but mean very different things.
 */
#[Scoped]
class CompanyContext
{
    private ?int $companyId = null;

    private bool $combined = false;

    private bool $resolved = false;

    /**
     * Constrain every company-tagged query to one company.
     */
    public function setCompany(Company|int $company): void
    {
        $this->companyId = $company instanceof Company ? $company->id : $company;
        $this->combined = false;
        $this->resolved = true;
    }

    /**
     * View every company at once. Only a parent-wide role may reach this state.
     */
    public function setCombined(): void
    {
        $this->companyId = null;
        $this->combined = true;
        $this->resolved = true;
    }

    /**
     * Forget the active company, returning to the unresolved state.
     */
    public function clear(): void
    {
        $this->companyId = null;
        $this->combined = false;
        $this->resolved = false;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Whether a company filter should be applied to company-tagged queries.
     */
    public function isConstrained(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * Whether a parent-wide role is deliberately viewing all companies.
     */
    public function isCombined(): bool
    {
        return $this->combined;
    }

    /**
     * Whether any company context has been established for this request.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
