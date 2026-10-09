<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Support\Branding;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('groupColor')
                ->label('Warna "Semua perusahaan"')
                ->icon(Heroicon::OutlinedSwatch)
                ->color('gray')
                ->modalHeading('Warna untuk tampilan "Semua perusahaan"')
                ->modalDescription('Dipakai sebagai warna utama aplikasi saat semua perusahaan dilihat bersamaan. Kosongkan untuk warna bawaan.')
                ->fillForm(fn (): array => ['color' => app(Branding::class)->groupColor()])
                ->schema([
                    ColorPicker::make('color')
                        ->label('Warna')
                        ->regex(Branding::COLOR_PATTERN)
                        ->validationMessages(['regex' => 'Gunakan warna heksadesimal enam digit, mis. #1d4ed8.']),
                ])
                ->action(function (array $data): void {
                    $branding = app(Branding::class);
                    $before = $branding->groupColor();
                    $color = filled($data['color'] ?? null) ? (string) $data['color'] : null;

                    $branding->setGroupColor($color);

                    activity('settings')
                        ->causedBy(auth()->user())
                        ->withProperties(['before' => $before, 'after' => $color])
                        ->log('Group colour changed');

                    Notification::make()->title('Warna disimpan.')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
