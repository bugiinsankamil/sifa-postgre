<?php

namespace App\Filament\Resources\Inventaris;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Inventaris\Pages\CreateGudang;
use App\Filament\Resources\Inventaris\Pages\EditGudang;
use App\Filament\Resources\Inventaris\Pages\ListGudang;
use App\Models\Cabang;
use App\Models\Gudang;
use App\Models\Pegawai;
use App\Models\Ruang;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GudangResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = Gudang::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|UnitEnum|null $navigationGroup = 'Inventaris';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Gudang';
    protected static ?string $modelLabel = 'gudang';
    protected static ?string $pluralModelLabel = 'gudang';
    protected static ?string $slug = 'inventaris/gudang';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data gudang')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                TextInput::make('kode')->required()->maxLength(30),
                TextInput::make('nama')->required()->maxLength(255),
                Select::make('ruang_id')->label('Ruang')
                    ->options(fn () => Ruang::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('penanggung_jawab_id')->label('Penanggung Jawab')
                    ->options(fn () => Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->nullable()->searchable(),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('ruang.nama')->label('Ruang'),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGudang::route('/'),
            'create' => CreateGudang::route('/create'),
            'edit' => EditGudang::route('/{record}/edit'),
        ];
    }
}
