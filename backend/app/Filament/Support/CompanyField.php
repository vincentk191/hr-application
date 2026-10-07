<?php

namespace App\Filament\Support;

use App\Models\Company;
use App\Support\CompanyContext;
use Filament\Forms\Components\Select;

/**
 * The company selector for records that belong to one company.
 *
 * BelongsToCompany fills company_id from the active company, but a parent-wide administrator
 * viewing every company at once has no active company, so the field has to ask. While a single
 * company is being viewed it offers only that one, matching what the scope would allow anyway.
 * The company cannot be changed afterwards: its departments, positions and employees would be
 * left pointing at a different company's records.
 */
class CompanyField
{
    public static function make(): Select
    {
        return Select::make('company_id')
            ->label('Perusahaan')
            ->options(function (): array {
                $activeId = app(CompanyContext::class)->companyId();

                return Company::query()
                    ->active()
                    ->when($activeId !== null, fn ($query) => $query->whereKey($activeId))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all();
            })
            ->default(fn (): ?int => app(CompanyContext::class)->companyId())
            ->required()
            ->live()
            ->disabled(fn (string $operation): bool => $operation === 'edit')
            ->dehydrated(fn (string $operation): bool => $operation === 'create');
    }
}
