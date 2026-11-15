<?php

namespace App\Filament\Resources\Inventaris;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Inventaris\Pages\CreateBarang;
use App\Filament\Resources\Inventaris\Pages\EditBarang;
use App\Filament\Resources\Inventaris\Pages\ListBarang;
use App\Models\Barang;
use App\Models\KategoriAset;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BarangResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = Barang::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static string|UnitEnum|null $navigationGroup = 'Inventaris';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Katalog Barang';
    protected static ?string $modelLabel = 'barang';
    protected static ?string $pluralModelLabel = 'barang';
    protected static ?string $slug = 'inventaris/barang';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data barang')->columns(2)->schema([
                TextInput::make('kode')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('nama')->required()->maxLength(255),
                Select::make('kategori_id')->label('Kategori')
                    ->options(fn () => KategoriAset::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('jenis')->options([
                    'aset_tetap' => 'Aset Tetap',
                    'habis_pakai' => 'Habis Pakai',
                ])->required(),
                TextInput::make('satuan_ref')->label('Satuan')->required()->maxLength(30),
                TextInput::make('merek')->maxLength(100),
                TextInput::make('harga_standar')->numeric()->prefix('Rp'),
                TextInput::make('stok_minimum')->numeric()->minValue(0),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
                Textarea::make('spesifikasi')->columnSpan(2)->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('kategori.nama')->label('Kategori')->searchable(),
                TextColumn::make('jenis')->badge()->color(fn (string $state) => match ($state) {
                    'aset_tetap' => 'info',
                    'habis_pakai' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('satuan_ref')->label('Satuan'),
                TextColumn::make('harga_standar')->money('IDR')->sortable(),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('kategori_id')->label('Kategori')
                    ->options(fn () => KategoriAset::query()->orderBy('nama')->pluck('nama', 'id')),
                SelectFilter::make('jenis')->options([
                    'aset_tetap' => 'Aset Tetap',
                    'habis_pakai' => 'Habis Pakai',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBarang::route('/'),
            'create' => CreateBarang::route('/create'),
            'edit' => EditBarang::route('/{record}/edit'),
        ];
    }
}
