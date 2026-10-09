<?php

namespace App\Filament\Resources\RequestTypes\Pages;

use App\Filament\Resources\RequestTypes\RequestTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditRequestType extends EditRecord
{
    protected static string $resource = RequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [RequestTypeResource::removeAction()];
    }
}
