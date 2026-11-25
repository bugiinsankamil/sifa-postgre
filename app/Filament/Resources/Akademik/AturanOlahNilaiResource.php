<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateAturanOlahNilai;
use App\Filament\Resources\Akademik\Pages\EditAturanOlahNilai;
use App\Filament\Resources\Akademik\Pages\ListAturanOlahNilai;
use App\Models\AturanOlahNilai;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\Sekolah;
use App\Models\SkalaPenilaian;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Support\CabangKonteks;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AturanOlahNilaiResource extends Resource
{
    protected static ?string $model = AturanOlahNilai::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sliders';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 12;
    protected static ?string $navigationLabel = 'Aturan Olah Nilai';
    protected static ?string $slug = 'akademik/aturan-olah-nilai';
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->required(),
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'tahun')->searchable()->required(),
            Select::make('kurikulum_id')->label('Kurikulum')->relationship('kurikulum', 'kode')->searchable()->nullable(),
            Select::make('tingkat_id')->label('Tingkat')->relationship('tingkat', 'nama')->searchable()->nullable(),
            Select::make('mata_pelajaran_id')->label('Mata Pelajaran')->relationship('mataPelajaran', 'nama')->searchable()->nullable(),
            Select::make('metode')->label('Metode')->enum([
                'rata_berbobot' => 'Rata-Rata Berbobot',
                'rata_sumatif' => 'Rata-Rata Sumatif',
                'nilai_tertinggi' => 'Nilai Tertinggi',
                'formula' => 'Formula',
            ])->default('rata_berbobot'),
            TextInput::make('kkm')->label('KKM')->numeric()->nullable(),
            Select::make('skala_id')->label('Skala')->relationship('skala', 'kode')->searchable()->nullable(),
            Select::make('pembulatan')->label('Pembulatan')->enum([
                'standar' => 'Standar',
                'atas' => 'Ke Atas',
                'bawah' => 'Ke Bawah',
            ])->default('standar'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('tahunAjaran.tahun')->label('Tahun Ajaran')->sortable()->searchable(),
                TextColumn::make('kurikulum.kode')->label('Kurikulan')->sortable()->searchable(),
                TextColumn::make('tingkat.nama')->label('Tingkat')->sortable()->searchable(),
                TextColumn::make('mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('metode')->label('Metode')->sortable(),
                TextColumn::make('kkm')->label('KKM')->sortable(),
                TextColumn::make('skala.kode')->label('Skala')->sortable()->searchable(),
            ])
            ->defaultSort('sekolah_id', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAturanOlahNilai::route('/'),
            'create' => CreateAturanOlahNilai::route('/create'),
            'edit' => EditAturanOlahNilai::route('/{record}/edit'),
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
        return parent::getEloquentQuery()->with(['sekolah', 'tahunAjaran', 'kurikulum', 'tingkat', 'mataPelajaran', 'skala']);
    }
}