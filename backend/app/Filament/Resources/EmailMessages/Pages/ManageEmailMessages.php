<?php

namespace App\Filament\Resources\EmailMessages\Pages;

use App\Filament\Resources\EmailMessages\EmailMessageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailMessages extends ManageRecords
{
    protected static string $resource = EmailMessageResource::class;
}
