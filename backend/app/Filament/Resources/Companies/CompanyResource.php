<?php

namespace App\Filament\Resources\Companies;

use App\Enums\Permission;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Models\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    protected static ?int $navigationSort = 1;

    /**
     * CompanyPolicy lets every account see the company list, because the switcher needs it. The
     * admin panel is narrower: only accounts that can manage companies get this screen.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageCompanies->value) === true;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
