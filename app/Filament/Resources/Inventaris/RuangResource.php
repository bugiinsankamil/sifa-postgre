<?php

namespace App\Filament\Resources\Inventaris;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Inventaris\Pages\CreateRuang;
use App\Filament\Resources\Inventaris\Pages\EditRuang;
use App\Filament\Resources\Inventaris\Pages\ListRuang;
use App\Models\Bangunan;
use App\Models\Cabang;
use App\Models\Pegawai;
use App\Models\Ruang;
use App\Models\Sekolah;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RuangResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = Ruang::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static string|UnitEnum|null $navigationGroup = 'Inventaris';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Ruang';
    protected static ?string $modelLabel = 'ruang';
    protected static ?string $pluralModelLabel = 'ruang';
    protected static ?string $slug = 'inventaris/ruang';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data ruang')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                Select::make('sekolah_id')->label('Sekolah')
                    ->options(fn () => Sekolah::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('bangunan_id')->label('Bangunan')
                    ->options(fn () => Bangunan::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('kode')->required()->maxLength(30),
                TextInput::make('nama')->required()->maxLength(255),
                TextInput::make('lantai')->numeric()->minValue(0),
                Select::make('jenis_ref')->label('Jenis')->options([
                    'kelas' => 'Kelas',
                    'lab' => 'Laboratorium',
                    'perpustakaan' => 'Perpustakaan',
                    'kantor' => 'Kantor',
                    'masjid' => 'Masjid',
                    'aula' => 'Aula',
                    'gudang' => 'Gudang',
                    'lainnya' => 'Lainnya',
                ]),
                TextInput::make('luas_m2')->numeric()->label('Luas (m²)'),
                TextInput::make('kapasitas')->numeric()->minValue(0),
                Select::make('kondisi')->options([
                    'baik' => 'Baik',
                    'rusak_ringan' => 'Rusak Ringan',
                    'rusak_berat' => 'Rusak Berat',
                ])->default('baik')->required(),
                Select::make('penanggung_jawab_id')->label('Penanggung Jawab')
                    ->options(fn () => Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->nullable()->searchable(),
                Select::make('status')->options([
                    'aktif' => 'Aktif',
                    'nonaktif' => 'Nonaktif',
                ])->default('aktif')->required(),
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
                TextColumn::make('bangunan.nama')->label('Bangunan'),
                TextColumn::make('jenis_ref')->label('Jenis'),
                TextColumn::make('kondisi')->badge()->color(fn (string $state) => match ($state) {
                    'baik' => 'success',
                    'rusak_ringan' => 'warning',
                    'rusak_berat' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'aktif' => 'success',
                    'nonaktif' => 'gray',
                    default => 'gray',
                }),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('jenis_ref')->label('Jenis')->options([
                    'kelas' => 'Kelas',
                    'lab' => 'Laboratorium',
                    'perpustakaan' => 'Perpustakaan',
                    'kantor' => 'Kantor',
                    'masjid' => 'Masjid',
                    'aula' => 'Aula',
                    'gudang' => 'Gudang',
                    'lainnya' => 'Lainnya',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRuang::route('/'),
            'create' => CreateRuang::route('/create'),
            'edit' => EditRuang::route('/{record}/edit'),
        ];
    }
}
