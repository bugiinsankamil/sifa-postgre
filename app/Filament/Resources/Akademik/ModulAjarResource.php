<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateModulAjar;
use App\Filament\Resources\Akademik\Pages\EditModulAjar;
use App\Filament\Resources\Akademik\Pages\ListModulAjar;
use App\Models\ModulAjar;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
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

class ModulAjarResource extends Resource
{
    protected static ?string $model = ModulAjar::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationLabel = 'Modul Ajar';
    protected static ?string $slug = 'akademik/modul-ajar';
    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->required(),
            Select::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'tahun')->searchable()->required(),
            Select::make('semester_id')->label('Semester')->relationship('semester', 'nama')->searchable()->required(),
            Select::make('tingkat_id')->label('Tingkat')->relationship('tingkat', 'nama')->searchable()->required(),
            Select::make('pegawai_id')->label('Penyusun')->relationship('pegawai', 'orang.nama_lengkap')->searchable()->required(),
            TextInput::make('judul')->label('Judul Modul')->required()->maxLength(255),
            TextInput::make('alokasi_waktu')->label('Alokasi Waktu (menit)')->integer()->nullable(),
            Select::make('status')->label('Status')->enum([
                'draft' => 'Draft',
                'diajukan' => 'Diajukan',
                'disetujui' => 'Disetujui',
                'revisi' => 'Revisi',
            ])->default('draft'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('tahunAjaran.tahun')->label('Tahun Ajaran')->sortable()->searchable(),
                TextColumn::make('semester.nama')->label('Semester')->sortable(),
                TextColumn::make('tingkat.nama')->label('Tingkat')->sortable(),
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Penyusun')->searchable(),
                TextColumn::make('judul')->label('Judul')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->defaultSort('judul', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModulAjar::route('/'),
            'create' => CreateModulAjar::route('/create'),
            'edit' => EditModulAjar::route('/{record}/edit'),
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

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft';
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->status !== 'draft') {
            throw new \App\Exceptions\Akademik\ModulAjarException(
                'Modul ajar dengan status "' . $record->status . '" tidak bisa dihapus.'
            );
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sekolah', 'tahunAjaran', 'semester', 'tingkat', 'pegawai.orang']);
    }
}