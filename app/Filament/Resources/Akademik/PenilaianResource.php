<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreatePenilaian;
use App\Filament\Resources\Akademik\Pages\EditPenilaian;
use App\Filament\Resources\Akademik\Pages\ListPenilaian;
use App\Models\KomponenPenilaian;
use App\Models\Penilaian;
use App\Models\PenugasanMengajar;
use App\Models\Semester;
use App\Support\CabangKonteks;
use Filament\Forms\Components\DatePicker;
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

class PenilaianResource extends Resource
{
    protected static ?string $model = Penilaian::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Penilaian';
    protected static ?string $slug = 'akademik/penilaian';
    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('penugasan_mengajar_id')->label('Penugasan Mengajar')->relationship('penugasanMengajar', function ($query) {
                return $query->with(['mataPelajaran', 'rombel', 'pegawai.orang']);
            })->searchable()->required(),
            Select::make('semester_id')->label('Semester')->relationship('semester', 'nama')->searchable()->required(),
            Select::make('komponen_id')->label('Komponen')->relationship('komponen', 'nama')->searchable()->required(),
            TextInput::make('judul')->label('Judul')->required()->maxLength(255),
            DatePicker::make('tanggal')->label('Tanggal')->required(),
            TextInput::make('nilai_maks')->label('Nilai Maks')->numeric()->default(100),
            Select::make('status')->label('Status')->enum([
                'draft' => 'Draft',
                'final' => 'Final',
            ])->default('draft'),
            Select::make('sumber')->label('Sumber')->enum([
                'manual' => 'Manual',
                'tugas' => 'Tugas',
                'cbt' => 'CBT',
            ])->default('manual'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('penugasanMengajar.mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('penugasanMengajar.rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('semester.nama')->label('Semester')->sortable(),
                TextColumn::make('komponen.nama')->label('Komponen')->sortable()->searchable(),
                TextColumn::make('judul')->label('Judul')->searchable(),
                TextColumn::make('tanggal')->label('Tanggal')->date()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenilaian::route('/'),
            'create' => CreatePenilaian::route('/create'),
            'edit' => EditPenilaian::route('/{record}/edit'),
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
        return parent::getEloquentQuery()->with(['penugasanMengajar.mataPelajaran', 'penugasanMengajar.rombel', 'semester', 'komponen']);
    }
}