<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\JenisTagihan;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\TarifTagihan;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use BackedEnum;
use UnitEnum;

class TarifTagihanResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = TarifTagihan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Tarif Tagihan';
    protected static ?string $modelLabel = 'tarif tagihan';
    protected static ?string $pluralModelLabel = 'tarif tagihan';
    protected static ?string $slug = 'keuangan/tarif-tagihan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data tarif')->schema([
                Select::make('jenis_tagihan_id')->label('Jenis Tagihan')
                    ->options(fn () => JenisTagihan::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('sekolah_id')->label('Sekolah')
                    ->options(fn ($q) => \App\Models\Sekolah::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tahun_ajaran_id')->label('Tahun Ajaran')
                    ->options(fn ($q) => \App\Models\TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tingkat_id')->label('Tingkat (kosongkan untuk semua tingkat)')
                    ->options(fn ($q) => Tingkat::query()->orderBy('urutan')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('nominal')->label('Nominal')->numeric()->required(),
                Toggle::make('is_aktif')->label('Aktif'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenisTagihan.nama')->label('Jenis Tagihan'),
                TextColumn::make('sekolah.nama')->label('Sekolah'),
                TextColumn::make('tahunAjaran.nama')->label('Tahun Ajaran'),
                TextColumn::make('tingkat.nama')->label('Tingkat'),
                TextColumn::make('nominal')->money('idr'),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ])
            ->defaultSort('tahun_ajaran_id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListTarifTagihan::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateTarifTagihan::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditTarifTagihan::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewTarifTagihan::route('/{record}'),
        ];
    }
}
