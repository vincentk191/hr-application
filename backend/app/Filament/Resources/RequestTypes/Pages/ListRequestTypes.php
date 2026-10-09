<?php

namespace App\Filament\Resources\RequestTypes\Pages;

use App\Filament\Resources\RequestTypes\RequestTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequestTypes extends ListRecords
{
    protected static string $resource = RequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
