<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateJamPelajaran;
use App\Filament\Resources\Akademik\Pages\EditJamPelajaran;
use App\Filament\Resources\Akademik\Pages\ListJamPelajaran;
use App\Models\JamPelajaran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimeInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;

class JamPelajaranResource extends Resource
{
    protected static ?string $model = JamPelajaran::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Jam Pelajaran';
    protected static ?string $slug = 'akademik/jam-pelajaran';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->nullable(),
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'tahun')->searchable()->nullable(),
            Select::make('hari')->label('Hari')->enum(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'])->nullable(),
            TextInput::make('urutan')->label('Urutan')->integer()->nullable(),
            TimeInput::make('jam_mulai')->label('Jam Mulai')->nullable(),
            TimeInput::make('jam_selesai')->label('Jam Selesai')->nullable(),
            Select::make('tipe')->label('Tipe')->enum(['belajar', 'istirahat', 'ibadah'])->default('belajar'),
            TextInput::make('nama')->label('Nama/Jenis')->maxLength(50)->nullable(),
            Toggle::make('status')->label('Status')->enum(['aktif', 'nonaktif'])->default('aktif'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('tahunAjaran.tahun')->label('Tahun Ajaran')->sortable()->searchable(),
                TextColumn::make('hari')->label('Hari')->sortable(),
                TextColumn::make('urutan')->label('Urutan')->sortable(),
                TextColumn::make('jam_mulai')->label('Mulai')->sortable()->toggleable(),
                TextColumn::make('jam_selesai')->label('Selesai')->sortable()->toggleable(),
                TextColumn::make('tipe')->label('Tipe')->sortable(),
                TextColumn::make('nama')->label('Nama')->sortable()->searchable(),
                TextColumn::make('status')->label('Status')->toggle()->sortable(),
            ])
            ->defaultSort('hari', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJamPelajaran::route('/'),
            'create' => CreateJamPelajaran::route('/create'),
            'edit' => EditJamPelajaran::route('/{record}/edit'),
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
        // Jangan hapus jam pelajaran yang sudah ada jadwal; nonaktifkan sebagai pengganti.
        return !$record->jadwal()->exists();
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->jadwal()->exists()) {
            throw new \App\Exceptions\Akademik\JadwalException(
                'Jam pelajaran '.$record->nama.' sudah digunakan di jadwal. Nonaktifkan sebagai pengganti.'
            );
        }
    }
}