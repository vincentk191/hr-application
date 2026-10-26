<?php

namespace App\Filament\Resources\Branches;

use App\Enums\AttendanceSource;
use App\Enums\Permission;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Filament\Support\CompanyField;
use App\Models\Branch;
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
use Illuminate\Validation\Rules\Unique;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'cabang';

    protected static ?string $pluralModelLabel = 'Cabang';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            CompanyField::make(),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255)
                // HR may open a Cabang to set its attendance source; the name stays the System Admin's.
                ->disabled(fn (): bool => auth()->user()?->can(Permission::ManageCompanies->value) !== true)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('company_id', $get('company_id')),
                ),
            Select::make('attendance_source')
                ->label('Sumber absensi')
                ->helperText('Pemindai: HR mengunggah Laporan Absensi dari mesin di cabang ini. Rekapitulasi: cabang tanpa mesin, PIC-nya mengirim rekap per periode.')
                ->options(collect(AttendanceSource::cases())->mapWithKeys(fn (AttendanceSource $source): array => [$source->value => $source->label()])->all())
                ->default(AttendanceSource::Scanner->value)
                ->required()
                ->native(false),
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
                TextColumn::make('attendance_source')
                    ->label('Sumber absensi')
                    ->formatStateUsing(fn (AttendanceSource $state): string => $state->label()),
                TextColumn::make('employees_count')
                    ->label('Karyawan')
                    ->counts('employees'),
            ])
            ->recordActions([
                EditAction::make(),
                // BranchPolicy refuses the delete while anyone still belongs to it.
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
