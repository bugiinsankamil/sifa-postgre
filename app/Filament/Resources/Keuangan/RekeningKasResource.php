<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\EntitasAkuntansi;
use App\Models\RekeningKas;
use App\Models\Cabang;
use App\Models\Akun;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use BackedEnum;
use UnitEnum;

class RekeningKasResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = RekeningKas::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Rekening Kas & Bank';
    protected static ?string $modelLabel = 'rekening kas';
    protected static ?string $pluralModelLabel = 'rekening kas & bank';
    protected static ?string $slug = 'keuangan/rekening-kas';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data rekening')->columns(2)->schema([
                Select::make('entitas_id')->label('Entitas')
                    ->options(fn () => EntitasAkuntansi::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('nama')->required(),
                Select::make('tipe')->label('Tipe')->options(['kas' => 'Kas', 'bank' => 'Bank'])->required(),
                TextInput::make('bank_ref')->label('Bank'),
                TextInput::make('nomor_rekening')->label('Nomor Rekening'),
                TextInput::make('akun_id')->label('Akun')->numeric()->required(),
                TextInput::make('saldo_awal')->numeric()->default(0),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('nomor_rekening'),
                TextColumn::make('entitas.nama')->label('Entitas'),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('saldo_awal')->money('idr'),
                ToggleColumn::make('is_aktif'),
            ])
            ->defaultSort('nama', 'asc')
            ->filters([
                SelectFilter::make('tipe')->options(['kas' => 'Kas', 'bank' => 'Bank']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListRekeningKas::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateRekeningKas::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditRekeningKas::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewRekeningKas::route('/{record}'),
        ];
    }
}
