<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateLokasiAbsensi;
use App\Filament\Resources\Sdm\Pages\EditLokasiAbsensi;
use App\Filament\Resources\Sdm\Pages\ListLokasiAbsensi;
use App\Models\Cabang;
use App\Models\LokasiAbsensi;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LokasiAbsensiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = LokasiAbsensi::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Lokasi Absensi';
    protected static ?string $modelLabel = 'lokasi absensi';
    protected static ?string $pluralModelLabel = 'lokasi absensi';
    protected static ?string $slug = 'sdm/lokasi-absensi';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lokasi geofence absensi')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('nama')->required(),
                TextInput::make('latitude')->required()->numeric()->minValue(-90)->maxValue(90),
                TextInput::make('longitude')->required()->numeric()->minValue(-180)->maxValue(180),
                TextInput::make('radius_meter')->numeric()->default(100)->minValue(1)->suffix('meter'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('latitude'),
                TextColumn::make('longitude'),
                TextColumn::make('radius_meter')->suffix(' m'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLokasiAbsensi::route('/'),
            'create' => CreateLokasiAbsensi::route('/create'),
            'edit' => EditLokasiAbsensi::route('/{record}/edit'),
        ];
    }
}
