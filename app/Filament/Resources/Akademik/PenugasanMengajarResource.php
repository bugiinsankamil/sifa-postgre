<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreatePenugasanMengajar;
use App\Filament\Resources\Akademik\Pages\EditPenugasanMengajar;
use App\Filament\Resources\Akademik\Pages\ListPenugasanMengajar;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\Rombel;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Schemas\Schema;

class PenugasanMengajarResource extends Resource
{
    protected static ?string $model = PenugasanMengajar::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-chalkboard';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Penugasan Mengajar';
    protected static ?string $slug = 'akademik/penugasan-mengajar';
    protected static ?string $recordTitleAttribute = 'no_sk';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->nullable(),
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'tahun')->searchable()->nullable(),
            Select::make('semester_id')->label('Semester')->relationship('semester', 'nama')->searchable()->nullable(),
            Select::make('rombel_id')->label('Rombel')->relationship('rombel', 'nama')->searchable()->nullable(),
            Select::make('mata_pelajaran_id')->label('Mata Pelajaran')->relationship('mataPelajaran', 'nama')->searchable(),
            Select::make('pegawai_id')->label('Guru')->relationship('pegawai', 'nama')->searchable(),
            TextInput::make('no_sk')->label('No SK')->maxLength(50),
            Select::make('peran')->label('Peran')->enum(['utama', 'pendamping'])->default('utama'),
            Toggle::make('status')->label('Status')->enum(['aktif', 'nonaktif'])->default('aktif'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('tahunAjaran.tahun')->label('Tahun Ajaran')->sortable()->searchable(),
                TextColumn::make('semester.nama')->label('Semester')->sortable()->searchable(),
                TextColumn::make('rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('pegawai.nama')->label('Guru')->sortable()->searchable(),
                TextColumn::make('peran')->label('Peran')->sortable(),
                TextColumn::make('status')->label('Status')->toggle()->sortable(),
                TextColumn::make('no_sk')->label('No SK')->sortable()->searchable(),
            ])
            ->defaultSort('sekolah_id', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenugasanMengajar::route('/'),
            'create' => CreatePenugasanMengajar::route('/create'),
            'edit' => EditPenugasanMengajar::route('/{record}/edit'),
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
        // Jangan hapus penugasan yang sudah memiliki jadwal; nonaktifkan sebagai pengganti.
        return !$record->jadwal()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->jadwal()->exists()) {
            throw new \App\Exceptions\Akademik\JadwalException(
                'Penugasan '.$record->no_sk.' sudah memiliki jadwal. Nonaktifkan sebagai pengganti.'
            );
        }
    }
}