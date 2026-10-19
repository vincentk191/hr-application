<?php

namespace App\Filament\Resources\OffboardingItems\Pages;

use App\Filament\Resources\OffboardingItems\OffboardingItemResource;
use App\Support\Offboarding\OffboardingCatalog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffboardingItems extends ListRecords
{
    protected static string $resource = OffboardingItemResource::class;

    /**
     * Opening the list makes the default items if there are none, so there is something to edit rather than
     * an empty page; a case does the same when its checklist begins.
     */
    public function mount(): void
    {
        parent::mount();

        app(OffboardingCatalog::class)->ensureDefaults();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
