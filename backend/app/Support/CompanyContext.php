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
 * Four states matter, and they are not interchangeable:
 *
 * - **Constrained** — `$companyId` is set, so every company-tagged query is filtered to it.
 *   This is what a company-pinned user always gets, and what a parent-wide user gets while
 *   they have one company selected.
 * - **Combined** — a parent-wide role is deliberately viewing all companies at once, so no
 *   filter is applied (CORE-NAV-003).
 * - **A set** — a supervisor whose team spans companies is viewing all of the ones they reach at
 *   once. Queries are filtered to exactly those companies; it is never the unfiltered state.
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

    /** @var list<int>|null */
    private ?array $companyIds = null;

    /** The company the person belongs to, which new records fall back to while a set is active. */
    private ?int $homeCompanyId = null;

    /**
     * Constrain every company-tagged query to one company.
     */
    public function setCompany(Company|int $company): void
    {
        $this->companyId = $company instanceof Company ? $company->id : $company;
        $this->companyIds = null;
        $this->homeCompanyId = null;
        $this->combined = false;
        $this->resolved = true;
    }

    /**
     * View several companies at once, and only those: the ones a supervisor's team reaches.
     *
     * @param  list<int>  $companyIds
     */
    public function setCompanies(array $companyIds, int $homeCompanyId): void
    {
        $this->companyId = null;
        $this->companyIds = array_values(array_unique($companyIds));
        $this->homeCompanyId = $homeCompanyId;
        $this->combined = false;
        $this->resolved = true;
    }

    /**
     * View every company at once. Only a parent-wide role may reach this state.
     */
    public function setCombined(): void
    {
        $this->companyId = null;
        $this->companyIds = null;
        $this->homeCompanyId = null;
        $this->combined = true;
        $this->resolved = true;
    }

    /**
     * Forget the active company, returning to the unresolved state.
     */
    public function clear(): void
    {
        $this->companyId = null;
        $this->companyIds = null;
        $this->homeCompanyId = null;
        $this->combined = false;
        $this->resolved = false;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * The companies company-tagged queries are filtered to: the one active company, or the set being viewed.
     * Null when nothing is filtered (combined, or no context at all).
     *
     * @return list<int>|null
     */
    public function companyIds(): ?array
    {
        if ($this->companyId !== null) {
            return [$this->companyId];
        }

        return $this->companyIds;
    }

    /**
     * The company a new record is made in: the active one, or while a set is active the person's own.
     */
    public function creationCompanyId(): ?int
    {
        return $this->companyId ?? $this->homeCompanyId;
    }

    /**
     * Whether a company filter should be applied to company-tagged queries.
     */
    public function isConstrained(): bool
    {
        return $this->companyId !== null || $this->companyIds !== null;
    }

    /**
     * Whether several companies are being viewed together (a supervisor's reach), as opposed to a parent-wide combined view.
     */
    public function isSet(): bool
    {
        return $this->companyIds !== null;
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
