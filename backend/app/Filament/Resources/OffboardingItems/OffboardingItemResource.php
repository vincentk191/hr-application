<?php

namespace App\Filament\Resources\OffboardingItems;

use App\Enums\OffboardingItemKind;
use App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem;
use App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem;
use App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems;
use App\Filament\Support\CompanyField;
use App\Models\OffboardingItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The master list of offboarding items (OFF-TPL): what is usually handed back or switched off, each in a
 * department. On a case, the supervisor of that department owns it. An item with no department is HR
 * Admin's. Retiring an item (switching it off) keeps it on cases that already chose it.
 */
class OffboardingItemResource extends Resource
{
    protected static ?string $model = OffboardingItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'item offboarding';

    protected static ?string $pluralModelLabel = 'Item offboarding';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            CompanyField::make(),
            TextInput::make('name')->label('Nama item')->required()->maxLength(150),
            Select::make('kind')
                ->label('Jenis')
                ->options(collect(OffboardingItemKind::cases())->mapWithKeys(fn (OffboardingItemKind $kind): array => [$kind->value => $kind->label()])->all())
                ->required(),
            Select::make('department_id')
                ->label('Departemen (pemilik: kepala departemen)')
                ->relationship('department', 'name', fn (Builder $query, Get $get) => $query->where('company_id', $get('company_id')))
                ->searchable()
                ->preload()
                ->placeholder('Tanpa departemen (dimiliki Admin HR)'),
            TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Dipakai pada kasus baru')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Item')->searchable()->sortable(),
                TextColumn::make('kind')->label('Jenis')->formatStateUsing(fn (OffboardingItemKind $state): string => $state->label()),
                TextColumn::make('department.name')->label('Departemen')->placeholder('Tanpa departemen'),
                TextColumn::make('company.name')->label('Perusahaan'),
                IconColumn::make('is_active')->label('Dipakai')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffboardingItems::route('/'),
            'create' => CreateOffboardingItem::route('/create'),
            'edit' => EditOffboardingItem::route('/{record}/edit'),
        ];
    }
}
