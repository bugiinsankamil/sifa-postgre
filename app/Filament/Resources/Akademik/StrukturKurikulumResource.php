<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateStrukturKurikulum;
use App\Filament\Resources\Akademik\Pages\EditStrukturKurikulum;
use App\Filament\Resources\Akademik\Pages\ListStrukturKurikulum;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\StrukturKurikulum;
use App\Models\Tingkat;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StrukturKurikulumResource extends Resource
{
    protected static ?string $model = StrukturKurikulum::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Struktur Kurikulum';
    protected static ?string $modelLabel = 'struktur kurikulum';
    protected static ?string $pluralModelLabel = 'struktur kurikulum';
    protected static ?string $slug = 'akademik/struktur-kurikulum';
    protected static ?string $recordTitleAttribute = 'mata_pelajaran';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data struktur')->columns(2)->schema([
                Select::make('kurikulum_id')->label('Kurikulum')
                    ->options(fn () => Kurikulum::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tingkat_id')->label('Tingkat')
                    ->options(fn () => Tingkat::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('mata_pelajaran_id')->label('Mata Pelajaran')
                    ->options(fn () => MataPelajaran::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('jam_per_minggu')->label('Jam per minggu')
                    ->required()->integer()->minValue(1)->default(2),
                Toggle::make('is_wajib')->label('Wajib')->default(true),
                TextInput::make('kkm')->label('KKM')->numeric()->minValue(0)->default(75),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kurikulum.kode')->label('Kurikulum')->searchable(),
                TextColumn::make('tingkat.kode')->label('Tingkat')->searchable()->sortable(),
                TextColumn::make('mata_pelajaran.kode')->label('Mapel Kode')->searchable(),
                TextColumn::make('mata_pelajaran.nama')->label('Mata Pelajaran')->searchable(),
                TextColumn::make('jam_per_minggu')->label('Jam/Minggu')->sortable(),
                ToggleColumn::make('is_wajib')->label('Wajib'),
                TextColumn::make('kkm')->label('KKM')->sortable(),
            ])
            ->defaultSort(['kurikulum_id', 'tingkat_id', 'mata_pelajaran_id'], 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStrukturKurikulum::route('/'),
            'create' => CreateStrukturKurikulum::route('/create'),
            'edit' => EditStrukturKurikulum::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(\App\Support\CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'pj_program',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
