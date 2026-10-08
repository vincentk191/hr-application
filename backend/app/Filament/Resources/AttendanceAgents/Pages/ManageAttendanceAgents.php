<?php

namespace App\Filament\Resources\AttendanceAgents\Pages;

use App\Filament\Resources\AttendanceAgents\AttendanceAgentResource;
use App\Models\AttendanceAgent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceAgents extends ManageRecords
{
    protected static string $resource = AttendanceAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah agen')
                ->after(function (AttendanceAgent $record): void {
                    AttendanceAgentResource::showToken($record->issueToken(), $record->name);
                }),
        ];
    }
}
