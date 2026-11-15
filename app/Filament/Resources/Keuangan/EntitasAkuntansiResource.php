<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Filament\Resources\Keuangan\Pages\CreateEntitasAkuntansi;
use App\Filament\Resources\Keuangan\Pages\EditEntitasAkuntansi;
use App\Filament\Resources\Keuangan\Pages\ListEntitasAkuntansi;
use App\Models\Cabang;
use App\Models\EntitasAkuntansi;
use App\Models\Sekolah;
use App\Services\Keuangan\SinkronisasiEntitas;
use BackedEnum;
use Filament\Forms\Components\Select;
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

class EntitasAkuntansiResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = EntitasAkuntansi::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Entitas Akuntansi';
    protected static ?string $modelLabel = 'entitas';
    protected static ?string $pluralModelLabel = 'entitas akuntansi';
    protected static ?string $slug = 'keuangan/entitas';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data entitas')->columns(2)->schema([
                Select::make('tipe')->options([
                    'yayasan' => 'Yayasan',
                    'cabang' => 'Cabang',
                    'sekolah' => 'Sekolah',
                ])->required()->live(),
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->visible(fn (callable $get) => in_array($get('tipe'), ['cabang', 'sekolah']))
                    ->required(fn (callable $get) => in_array($get('tipe'), ['cabang', 'sekolah'])),
                Select::make('sekolah_id')->label('Sekolah')
                    ->options(fn (callable $get) => $get('cabang_id')
                        ? Sekolah::query()->where('cabang_id', $get('cabang_id'))->pluck('nama', 'id')
                        : [])
                    ->visible(fn (callable $get) => $get('tipe') === 'sekolah')
                    ->required(fn (callable $get) => $get('tipe') === 'sekolah'),
                TextInput::make('kode')->required()->maxLength(50),
                TextInput::make('nama')->required()->maxLength(255),
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
                TextColumn::make('tipe')->badge()->color(fn (string $state) => match ($state) {
                    'yayasan' => 'success',
                    'cabang' => 'info',
                    'sekolah' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('cabang.nama')->label('Cabang'),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('tipe')->options([
                    'yayasan' => 'Yayasan',
                    'cabang' => 'Cabang',
                    'sekolah' => 'Sekolah',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntitasAkuntansi::route('/'),
            'create' => CreateEntitasAkuntansi::route('/create'),
            'edit' => EditEntitasAkuntansi::route('/{record}/edit'),
        ];
    }
}
