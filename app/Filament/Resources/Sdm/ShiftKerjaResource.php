<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateShiftKerja;
use App\Filament\Resources\Sdm\Pages\EditShiftKerja;
use App\Filament\Resources\Sdm\Pages\ListShiftKerja;
use App\Models\Cabang;
use App\Models\ShiftKerja;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftKerjaResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = ShiftKerja::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Shift Kerja';
    protected static ?string $modelLabel = 'shift kerja';
    protected static ?string $pluralModelLabel = 'shift kerja';
    protected static ?string $slug = 'sdm/shift';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data shift')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('nama')->required(),
                TimePicker::make('jam_masuk')->required()->seconds(false),
                TimePicker::make('jam_pulang')->required()->seconds(false)->after('jam_masuk'),
                TextInput::make('toleransi_menit')->numeric()->default(0)->minValue(0)->suffix('menit'),
                CheckboxList::make('hari_kerja')->label('Hari kerja')->options([
                    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                    5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
                ])->default([1, 2, 3, 4, 5])->columns(4)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('jam_masuk'),
                TextColumn::make('jam_pulang'),
                TextColumn::make('toleransi_menit')->suffix(' mnt'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShiftKerja::route('/'),
            'create' => CreateShiftKerja::route('/create'),
            'edit' => EditShiftKerja::route('/{record}/edit'),
        ];
    }
}
