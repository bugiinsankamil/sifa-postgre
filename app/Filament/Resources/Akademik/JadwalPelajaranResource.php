<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\Akademik\Pages\EditJadwalPelajaran;
use App\Filament\Resources\Akademik\Pages\ListJadwalPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\Ruang;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;

class JadwalPelajaranResource extends Resource
{
    protected static ?string $model = JadwalPelajaran::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-bars-3';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Jadwal Pelajaran';
    protected static ?string $slug = 'akademik/jadwal-pelajaran';
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('penugasan_mengajar_id')->label('Penugasan Mengajar')->relationship('penugasanMengajar', function ($query) {
                return $query->with(['pegawai', 'mataPelajaran', 'rombel']);
            })->searchable(),
            Select::make('jam_pelajaran_id')->label('Jam Pelajaran')->relationship('jamPelajaran', 'nama')->searchable(),
            Select::make('ruang_id')->label('Ruang')->relationship('ruang', 'nama')->searchable()->nullable(),
            Select::make('semester_id')->label('Semester')->relationship('semester', 'nama')->searchable(),
            Toggle::make('status')->label('Status')->enum(['plan', 'aktif', 'selesai', 'diubah'])->default('plan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('penugasanMengajar.pegawai.nama')->label('Guru')->sortable()->searchable(),
                TextColumn::make('penugasanMengajar.mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('penugasanMengajar.rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('jamPelajaran.hari')->label('Hari')->sortable(),
                TextColumn::make('jamPelajaran.urutan')->label('Urutan')->sortable(),
                TextColumn::make('jamPelajaran.jam_mulai')->label('Mulai')->sortable(),
                TextColumn::make('jamPelajaran.jam_selesai')->label('Selesai')->sortable(),
                TextColumn::make('ruang.nama')->label('Ruang')->sortable()->searchable(),
                TextColumn::make('semester.nama')->label('Semester')->sortable()->searchable(),
                TextColumn::make('status')->label('Status')->toggle()->sortable(),
            ])
            ->defaultSort('status', 'asc')
            ->filters([
                // Filament filters can be added here
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwalPelajaran::route('/'),
            'create' => CreateJadwalPelajaran::route('/create'),
            'edit' => EditJadwalPelajaran::route('/{record}/edit'),
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
        // Jadwal yang sudah aktif tidak boleh dihapus; nonaktifkan sebagai pengganti.
        return $record->status === 'plan';
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->status !== 'plan') {
            throw new \App\Exceptions\Akademik\JadwalException(
                'Jadwal dengan status "' . $record->status . '" tidak bisa dihapus. Nonaktifkan sebagai pengganti.'
            );
        }
    }
}