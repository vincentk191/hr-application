<?php

namespace App\Support\Access;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\ReportingLine;
use Illuminate\Container\Attributes\Scoped;

/**
 * The companies an account can look at beyond its own, because people who report to it work there.
 *
 * Only a company-pinned Supervisor on the private entrance has any (User::reachesAcrossCompanies()); everyone else reaches
 * just their own company, and a parent-wide account is not asked at all. The reach is read from the reporting line each
 * time and never stored, so moving someone between managers moves it with them.
 *
 * It widens what a supervisor may *see* of a report. It is deliberately not a form of `canAccessCompany()`: no permission
 * is checked against it, so none of the HR-style actions (editing, documents, attendance, salary) open up in another company.
 */
#[Scoped]
class SupervisorReach
{
    /** @var array<int, list<int>> */
    private array $cache = [];

    public function __construct(private ReportingLine $line) {}

    /**
     * The company ids this account may switch between: its own first, then those its team works in. Empty for a
     * parent-wide account, whose reach is every company by another rule.
     *
     * @return list<int>
     */
    public function companyIds(User $user): array
    {
        if ($user->company_id === null) {
            return [];
        }

        return $this->cache[$user->id] ??= $this->resolve($user);
    }

    /**
     * Whether this account reaches the company, as its own or through its team.
     */
    public function covers(User $user, int $companyId): bool
    {
        return in_array($companyId, $this->companyIds($user), strict: true);
    }

    /**
     * Whether there is more than one company to choose between, which is what earns the "all companies" option.
     */
    public function spansCompanies(User $user): bool
    {
        return count($this->companyIds($user)) > 1;
    }

    /**
     * Forget what was worked out, for a new request: the reporting line may have changed since.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return list<int>
     */
    private function resolve(User $user): array
    {
        /** @var int $home */
        $home = $user->company_id;

        if (! $user->reachesAcrossCompanies()) {
            return [$home];
        }

        $own = Employee::acrossCompanies()->where('user_id', $user->id)->first();

        if (! $own instanceof Employee) {
            return [$home];
        }

        $others = Company::active()
            ->whereIn('id', $this->line->downlineCompanyIds($own))
            ->where('id', '!=', $home)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return [$home, ...array_values($others)];
    }
}
