<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateKompetensiGuru;
use App\Filament\Resources\Akademik\Pages\EditKompetensiGuru;
use App\Filament\Resources\Akademik\Pages\ListKompetensiGuru;
use App\Models\KompetensiGuru;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Tingkat;
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

class KompetensiGuruResource extends Resource
{
    protected static ?string $model = KompetensiGuru::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Kompetensi Guru';
    protected static ?string $modelLabel = 'kompetensi guru';
    protected static ?string $pluralModelLabel = 'kompetensi guru';
    protected static ?string $slug = 'akademik/kompetensi-guru';
    protected static ?string $recordTitleAttribute = 'pegawai';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kompetensi')->columns(2)->schema([
                Select::make('pegawai_id')->label('Pegawai')
                    ->options(fn () => Pegawai::query()->orderBy('nip_yayasan')->pluck('nip_yayasan', 'id'))
                    ->required()->searchable(),
                Select::make('mata_pelajaran_id')->label('Mata Pelajaran')
                    ->options(fn () => MataPelajaran::query()->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tingkat_id')->label('Tingkat (opsional)')
                    ->options(fn () => Tingkat::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('catatan')->maxLength(500),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nip_yayasan')->label('NIP')->searchable(),
                TextColumn::make('mata_pelajaran.kode')->label('Mapel')->searchable(),
                TextColumn::make('mata_pelajaran.nama')->label('Mata Pelajaran')->searchable(),
                TextColumn::make('tingkat.kode')->label('Tingkat'),
                TextColumn::make('catatan')->limit(50),
            ])
            ->defaultSort(['pegawai_id', 'mata_pelajaran_id'], 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKompetensiGuru::route('/'),
            'create' => CreateKompetensiGuru::route('/create'),
            'edit' => EditKompetensiGuru::route('/{record}/edit'),
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
