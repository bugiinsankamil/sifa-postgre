<?php

namespace App\Filament\Resources\Akademik;

use App\Exceptions\Akademik\IzinSiswaException;
use App\Filament\Resources\Akademik\Pages\CreateIzinSiswa;
use App\Filament\Resources\Akademik\Pages\EditIzinSiswa;
use App\Filament\Resources\Akademik\Pages\ListIzinSiswa;
use App\Models\IzinSiswa;
use App\Models\Orang;
use App\Models\Siswa;
use App\Support\CabangKonteks;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;

class IzinSiswaResource extends Resource
{
    protected static ?string $model = IzinSiswa::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-user-circle';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationLabel = 'Izin Siswa';
    protected static ?string $slug = 'akademik/izin-siswa';
    protected static ?string $recordTitleAttribute = 'tanggal_mulai';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('siswa_id')->label('Siswa')
                ->options(
                    Siswa::with('orang')
                        ->orderBy('nis_yayasan')
                        ->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->nis_yayasan.' - '.$s->orang?->nama_lengkap])
                )
                ->searchable()
                ->required(),
            DatePicker::make('tanggal_mulai')->label('Tanggal Mulai')->required(),
            DatePicker::make('tanggal_selesai')->label('Tanggal Selesai')->required(),
            Select::make('jenis')->label('Jenis')
                ->enum(['sakit' => 'Sakit', 'izin' => 'Izin'])
                ->required(),
            Textarea::make('alasan')->label('Alasan')->nullable(),
            Select::make('status')->label('Status')
                ->enum([
                    'diajukan' => 'Diajukan',
                    'disetujui' => 'Disetujui',
                    'ditolak' => 'Ditolak',
                    'dibatalkan' => 'Dibatalkan',
                ])
                ->default('diajukan'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_mulai')->label('Mulai')->date()->sortable(),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date()->sortable(),
                TextColumn::make('siswa.orang.nama_lengkap')->label('Siswa')->sortable()->searchable(),
                TextColumn::make('jenis')->label('Jenis')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('diajukanOleh.nama_lengkap')->label('Diajukan Oleh')->toggleable(),
            ])
            ->defaultSort('tanggal_mulai', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIzinSiswa::route('/'),
            'create' => CreateIzinSiswa::route('/create'),
            'edit' => EditIzinSiswa::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah',
            'wakil_kepala_sekolah', 'pj_program', 'admin', 'staf_servis',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->status !== 'diajukan') {
            throw new IzinSiswaException('Hanya izin yang masih diajukan yang dapat dihapus.');
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['siswa.orang', 'diajukanOleh']);
    }
}
