<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateKomponenPenilaian;
use App\Filament\Resources\Akademik\Pages\EditKomponenPenilaian;
use App\Filament\Resources\Akademik\Pages\ListKomponenPenilaian;
use App\Models\KomponenPenilaian;
use App\Models\Kurikulum;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Support\CabangKonteks;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KomponenPenilaianResource extends Resource
{
    protected static ?string $model = KomponenPenilaian::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Komponen Penilaian';
    protected static ?string $slug = 'akademik/komponen-penilaian';
    protected static ?string $recordTitleAttribute = 'kode';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->required(),
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'tahun')->searchable()->required(),
            Select::make('kurikulum_id')->label('Kurikulum')->relationship('kurikulum', 'kode')->searchable()->nullable(),
            TextInput::make('kode')->label('Kode')->required()->maxLength(50),
            TextInput::make('nama')->label('Nama')->required()->maxLength(100),
            Select::make('jenis')->label('Jenis')->enum([
                'formatif' => 'Formatif',
                'sumatif' => 'Sumatif',
                'uts' => 'UTS',
                'uas' => 'UAS',
                'sikap' => 'Sikap',
                'proyek' => 'Proyek',
                'praktik' => 'Praktik',
            ])->default('formatif'),
            TextInput::make('bobot')->label('Bobot')->numeric()->default(0),
            TextInput::make('urutan')->label('Urutan')->integer()->default(1),
            Toggle::make('is_aktif')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('tahunAjaran.tahun')->label('Tahun Ajaran')->sortable()->searchable(),
                TextColumn::make('kurikulum.kode')->label('Kurikulan')->sortable()->searchable(),
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->sortable(),
                TextColumn::make('bobot')->label('Bobot')->sortable(),
                TextColumn::make('is_aktif')->label('Aktif')->toggle()->sortable(),
            ])
            ->defaultSort('kode', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomponenPenilaian::route('/'),
            'create' => CreateKomponenPenilaian::route('/create'),
            'edit' => EditKomponenPenilaian::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'pj_program',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sekolah', 'tahunAjaran', 'kurikulum']);
    }
}