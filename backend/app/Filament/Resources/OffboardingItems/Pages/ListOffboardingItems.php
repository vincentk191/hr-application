<?php

namespace App\Filament\Resources\OffboardingItems\Pages;

use App\Filament\Resources\OffboardingItems\OffboardingItemResource;
use App\Support\CompanyContext;
use App\Support\Offboarding\OffboardingCatalog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffboardingItems extends ListRecords
{
    protected static string $resource = OffboardingItemResource::class;

    /**
     * Opening the list for one company makes its default items if it has none, so there is something to
     * edit rather than an empty page; a case does the same when its checklist begins.
     */
    public function mount(): void
    {
        parent::mount();

        $company = app(CompanyContext::class)->companyId() ?? auth()->user()?->company_id;

        if ($company !== null) {
            app(OffboardingCatalog::class)->ensureDefaults((int) $company);
        }
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
