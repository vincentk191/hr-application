<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Departments\Pages\EditDepartment;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Support\CompanyField;
use App\Models\Department;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $modelLabel = 'departemen';

    protected static ?string $pluralModelLabel = 'Departemen';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            CompanyField::make(),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('company_id', $get('company_id')),
                ),
            Select::make('supervisor_employee_id')
                ->label('Kepala departemen')
                ->relationship(
                    'supervisor',
                    'full_name',
                    fn (Builder $query, Get $get) => $query->where('company_id', $get('company_id')),
                )
                ->getOptionLabelFromRecordUsing(fn (Employee $employee): string => "{$employee->full_name} ({$employee->employee_number})")
                ->searchable(['full_name', 'employee_number'])
                ->preload()
                ->placeholder('Belum ditentukan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->sortable(),
                TextColumn::make('supervisor.full_name')
                    ->label('Kepala departemen')
                    ->placeholder('—'),
                TextColumn::make('employees_count')
                    ->label('Karyawan')
                    ->counts('employees'),
            ])
            ->recordActions([
                EditAction::make(),
                // DepartmentPolicy refuses the delete while anyone still belongs to it.
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
