<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Support\Branding;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('brand_color')
                    ->label('Warna perusahaan')
                    ->helperText('Warna utama aplikasi saat perusahaan ini dipilih. Kosongkan untuk warna bawaan.')
                    ->regex(Branding::COLOR_PATTERN)
                    ->validationMessages(['regex' => 'Gunakan warna heksadesimal enam digit, mis. #1d4ed8.']),
                FileUpload::make('logo_path')
                    ->label('Logo (mode terang)')
                    ->helperText('PNG, JPG, SVG atau WebP, maksimal 2 MB. Tampil di bilah atas saat perusahaan ini dipilih.')
                    ->image()
                    ->disk('public')
                    ->directory('company-logos')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                    ->maxSize(2048),
                FileUpload::make('logo_dark_path')
                    ->label('Logo (mode gelap)')
                    ->helperText('Opsional, untuk latar gelap. Kosongkan untuk memakai logo mode terang di kedua mode.')
                    ->image()
                    ->disk('public')
                    ->directory('company-logos')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                    ->maxSize(2048),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Perusahaan tidak dihapus, hanya dinonaktifkan.')
                    ->default(true),
                // Every employee belongs to a Cabang, so a company starts with at least one. Later ones,
                // for a new office, are added under Cabang in the menu, where a Cabang still holding
                // employees cannot be deleted.
                Repeater::make('branches')
                    ->label('Cabang')
                    ->helperText('Kantor perusahaan di tiap kota. Cabang berikutnya ditambahkan lewat menu Cabang.')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama cabang')
                            ->required()
                            ->maxLength(255)
                            ->distinct(),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah cabang')
                    ->visibleOn('create'),
            ]);
    }
}
