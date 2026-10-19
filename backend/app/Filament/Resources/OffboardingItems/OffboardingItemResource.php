<?php

namespace App\Filament\Resources\OffboardingItems;

use App\Enums\OffboardingItemKind;
use App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem;
use App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem;
use App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OffboardingItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The master list of offboarding items (OFF-TPL): what is usually handed back or switched off. The list
 * belongs to the whole group, since a department such as IT can serve every company. An item may name the
 * person who owns it, from any company, and their job title and department are read from their record; with
 * no one named, the supervisor of the department it names (in the leaver's company) owns it, and failing
 * that HR Admin. Retiring an item keeps it on cases that already chose it.
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
            TextInput::make('name')->label('Nama item')->required()->maxLength(150),
            Select::make('kind')
                ->label('Jenis')
                ->options(collect(OffboardingItemKind::cases())->mapWithKeys(fn (OffboardingItemKind $kind): array => [$kind->value => $kind->label()])->all())
                ->required(),
            Select::make('owner_employee_id')
                ->label('Pemilik (opsional)')
                ->helperText('Orang yang bertanggung jawab atas item ini, dari perusahaan mana pun. Jabatan dan departemennya diambil dari data karyawan. Kosongkan untuk memakai kepala departemen di bawah.')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Employee::acrossCompanies()
                    ->whereNotNull('user_id')
                    ->where(fn ($query) => $query->where('full_name', 'like', "%{$search}%")->orWhere('employee_number', 'like', "%{$search}%"))
                    ->orderBy('full_name')->limit(30)->get()
                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => self::ownerLabel($employee)])->all())
                ->getOptionLabelUsing(fn ($value): ?string => ($employee = Employee::acrossCompanies()->whereKey($value)->first()) === null ? null : self::ownerLabel($employee))
                ->placeholder('Tanpa pemilik tertentu'),
            Select::make('department_name')
                ->label('Departemen (kepala departemennya memiliki item ini bila tanpa pemilik)')
                ->options(fn (): array => Department::acrossCompanies()->orderBy('name')->pluck('name', 'name')->all())
                ->searchable()
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
                TextColumn::make('owner.full_name')->label('Pemilik')->placeholder('Kepala departemen'),
                TextColumn::make('department_name')->label('Departemen')->placeholder('Tanpa departemen'),
                IconColumn::make('is_active')->label('Dipakai')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('sort_order');
    }

    /**
     * "Ivan Pratama · IT Support · IT · CMP-A": who, their job title and department, and company.
     */
    private static function ownerLabel(Employee $employee): string
    {
        $employee->loadMissing(['position' => fn ($query) => $query->withoutGlobalScopes(), 'department' => fn ($query) => $query->withoutGlobalScopes(), 'company']);

        return collect([$employee->full_name, $employee->position?->name, $employee->department?->name, $employee->company?->code])->filter()->implode(' · ');
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
