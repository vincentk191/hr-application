<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    /**
     * Everything the SPA needs after sign-in: who, what they may do, and which company they are
     * looking at. The JSON counterpart of the props HandleInertiaRequests shares.
     */
    public function __invoke(Request $request, CompanyContext $context): JsonResponse
    {
        $user = $request->user();

        $companies = Company::active()
            ->when(! $user->isParentWide(), fn ($query) => $query->whereKey($user->company_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            ],
            'companySwitcher' => [
                'companies' => $companies->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'code' => $company->code,
                    'name' => $company->name,
                ])->all(),
                'selected' => $context->isCombined()
                    ? SetActiveCompany::COMBINED
                    : (string) $context->companyId(),
                'canCombine' => $user->isParentWide(),
                'locked' => ! $user->isParentWide(),
            ],
        ]]);
    }
}
