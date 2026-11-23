<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateTujuanPembelajaran;
use App\Filament\Resources\Akademik\Pages\EditTujuanPembelajaran;
use App\Filament\Resources\Akademik\Pages\ListTujuanPembelajaran;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\Tingkat;
use App\Models\TujuanPembelajaran;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TujuanPembelajaranResource extends Resource
{
    protected static ?string $model = TujuanPembelajaran::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-target';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Tujuan Pembelajaran';
    protected static ?string $modelLabel = 'tujuan pembelajaran';
    protected static ?string $pluralModelLabel = 'tujuan pembelajaran';
    protected static ?string $slug = 'akademik/tujuan-pembelajaran';
    protected static ?string $recordTitleAttribute = 'kode';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data tujuan')->columns(2)->schema([
                Select::make('kurikulum_id')->label('Kurikulum')
                    ->options(fn () => Kurikulum::query()->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('mata_pelajaran_id')->label('Mata Pelajaran')
                    ->options(fn () => MataPelajaran::query()->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tingkat_id')->label('Tingkat')
                    ->options(fn () => Tingkat::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('fase')->label('Fase')->maxLength(10)->default('A'),
                TextInput::make('kode')->label('Kode')->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('elemen')->label('Elemen')->maxLength(100),
                TextInput::make('uraian')->label('Uraian'),
                TextInput::make('urutan')->label('Urutan')->integer()->default(1),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kurikulum.kode')->label('Kurikulum')->searchable(),
                TextColumn::make('mata_pelajaran.kode')->label('Mapel')->searchable(),
                TextColumn::make('tingkat.kode')->label('Tingkat')->searchable(),
                TextColumn::make('fase')->label('Fase'),
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('elemen')->label('Elemen')->limit(50),
                TextColumn::make('uraian')->label('Uraian')->limit(80),
                TextColumn::make('urutan')->label('Urutan')->sortable(),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ])
            ->defaultSort(['kurikulum_id', 'mata_pelajaran_id', 'tingkat_id', 'urutan'], 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTujuanPembelajaran::route('/'),
            'create' => CreateTujuanPembelajaran::route('/create'),
            'edit' => EditTujuanPembelajaran::route('/{record}/edit'),
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
