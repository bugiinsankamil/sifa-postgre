<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Filament\Resources\Keuangan\Pages\CreateAkun;
use App\Filament\Resources\Keuangan\Pages\EditAkun;
use App\Filament\Resources\Keuangan\Pages\ListAkun;
use App\Models\Akun;
use App\Models\EntitasAkuntansi;
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

class AkunResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = Akun::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Bagan Akun';
    protected static ?string $modelLabel = 'akun';
    protected static ?string $pluralModelLabel = 'bagan akun';
    protected static ?string $slug = 'keuangan/akun';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data akun')->columns(2)->schema([
                Select::make('entitas_id')->label('Entitas (kosong = standar yayasan)')
                    ->options(fn () => EntitasAkuntansi::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('kode')->required()->maxLength(50),
                TextInput::make('nama')->required()->maxLength(255),
                Select::make('tipe')->options([
                    'aset' => 'Aset',
                    'kewajiban' => 'Kewajiban',
                    'ekuitas' => 'Ekuitas',
                    'pendapatan' => 'Pendapatan',
                    'beban' => 'Beban',
                ])->required(),
                Select::make('induk_id')->label('Akun Induk')
                    ->options(fn () => Akun::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('saldo_normal')->options([
                    'debit' => 'Debit',
                    'kredit' => 'Kredit',
                ])->required(),
                Toggle::make('is_header')->label('Header (tidak bisa bertransaksi)'),
                Toggle::make('is_antar_entitas')->label('Antar Entitas'),
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
                    'aset' => 'info',
                    'kewajiban' => 'warning',
                    'ekuitas' => 'success',
                    'pendapatan' => 'success',
                    'beban' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('saldo_normal')->label('Saldo Normal'),
                IconColumn::make('is_header')->boolean()->label('Header'),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('tipe')->options([
                    'aset' => 'Aset',
                    'kewajiban' => 'Kewajiban',
                    'ekuitas' => 'Ekuitas',
                    'pendapatan' => 'Pendapatan',
                    'beban' => 'Beban',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAkun::route('/'),
            'create' => CreateAkun::route('/create'),
            'edit' => EditAkun::route('/{record}/edit'),
        ];
    }
}
