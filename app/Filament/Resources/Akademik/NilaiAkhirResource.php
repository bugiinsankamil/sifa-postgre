<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateNilaiAkhir;
use App\Filament\Resources\Akademik\Pages\EditNilaiAkhir;
use App\Filament\Resources\Akademik\Pages\ListNilaiAkhir;
use App\Models\NilaiAkhir;
use App\Models\PenugasanMengajar;
use App\Models\Semester;
use App\Models\Siswa;
use App\Support\CabangKonteks;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NilaiAkhirResource extends Resource
{
    protected static ?string $model = NilaiAkhir::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Nilai Akhir';
    protected static ?string $slug = 'akademik/nilai-akhir';
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('penugasan_mengajar_id')->label('Penugasan Mengajar')->relationship('penugasanMengajar', function ($query) {
                return $query->with(['mataPelajaran', 'rombel', 'pegawai.orang']);
            })->searchable()->required(),
            Select::make('siswa_id')->label('Siswa')->relationship('siswa', 'nis_yayasan')->searchable()->required(),
            Select::make('semester_id')->label('Semester')->relationship('semester', 'nama')->searchable()->required(),
            TextInput::make('nilai_akhir')->label('Nilai Akhir')->numeric()->nullable(),
            Select::make('skala_butir_id')->label('Skala Butir')->relationship('skalaButir', 'label')->searchable()->nullable(),
            TextInput::make('deskripsi')->label('Deskripsi')->nullable()->maxLength(255),
            TextInput::make('kkm')->label('KKM')->numeric()->nullable(),
            Toggle::make('is_tuntas')->label('Tuntas')->default(false),
            Select::make('status')->label('Status')->enum([
                'draft' => 'Draft',
                'final' => 'Final',
            ])->default('draft'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('penugasanMengajar.mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('penugasanMengajar.rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('siswa.nis_yayasan')->label('NIS')->searchable(),
                TextColumn::make('semester.nama')->label('Semester')->sortable(),
                TextColumn::make('nilai_akhir')->label('Nilai Akhir')->sortable(),
                TextColumn::make('kkm')->label('KKM')->sortable(),
                TextColumn::make('is_tuntas')->label('Tuntas')->toggle()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->defaultSort('nilai_akhir', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNilaiAkhir::route('/'),
            'create' => CreateNilaiAkhir::route('/create'),
            'edit' => EditNilaiAkhir::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'pj_program', 'guru',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate() && $record->status === 'draft';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['penugasanMengajar.mataPelajaran', 'penugasanMengajar.rombel', 'siswa', 'semester']);
    }
}