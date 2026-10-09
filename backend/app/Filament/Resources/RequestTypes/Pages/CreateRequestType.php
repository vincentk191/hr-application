<?php

namespace App\Filament\Resources\RequestTypes\Pages;

use App\Filament\Resources\RequestTypes\RequestTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequestType extends CreateRecord
{
    protected static string $resource = RequestTypeResource::class;
}
