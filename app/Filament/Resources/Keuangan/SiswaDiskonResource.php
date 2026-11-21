<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\DiskonBeasiswa;
use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\SiswaDiskon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use BackedEnum;
use UnitEnum;

class SiswaDiskonResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = SiswaDiskon::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Diskon Siswa';
    protected static ?string $modelLabel = 'diskon siswa';
    protected static ?string $pluralModelLabel = 'diskon siswa';
    protected static ?string $slug = 'keuangan/siswa-diskon';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data diskon siswa')->schema([
                Select::make('siswa_id')->label('Siswa')
                    ->options(fn () => Siswa::query()->orderBy('nis_yayasan')->pluck('nis_yayasan', 'id'))
                    ->required()->searchable(),
                Select::make('diskon_id')->label('Diskon / Beasiswa')
                    ->options(fn () => DiskonBeasiswa::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('jenis_tagihan_id')->label('Jenis Tagihan (kosongkan untuk semua)')
                    ->options(fn () => JenisTagihan::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                DatePicker::make('tanggal_mulai')->required(),
                DatePicker::make('tanggal_akhir')->nullable(),
                TextInput::make('no_sk')->label('Nomor SK')->nullable(),
                Toggle::make('is_aktif') // placeholder, model pakai status
                    ->label('Aktif')
                    ->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('siswa.nis_yayasan')->label('Siswa (NIS)'),
                TextColumn::make('diskon.nama')->label('Diskon / Beasiswa'),
                TextColumn::make('jenisTagihan.nama')->label('Jenis Tagihan'),
                TextColumn::make('tanggal_mulai')->date('d/m/Y'),
                TextColumn::make('tanggal_akhir')->date('d/m/Y')->nullable(),
                TextColumn::make('status')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListSiswaDiskon::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateSiswaDiskon::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditSiswaDiskon::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewSiswaDiskon::route('/{record}'),
        ];
    }
}