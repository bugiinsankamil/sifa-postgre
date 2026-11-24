<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateJurnalMengajar;
use App\Filament\Resources\Akademik\Pages\EditJurnalMengajar;
use App\Filament\Resources\Akademik\Pages\ListJurnalMengajar;
use App\Models\JurnalMengajar;
use App\Models\JamPelajaran;
use App\Models\Pegawai;
use App\Models\PenugasanMengajar;
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

class JurnalMengajarResource extends Resource
{
    protected static ?string $model = JurnalMengajar::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-calendar';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Jurnal Mengajar';
    protected static ?string $slug = 'akademik/jurnal-mengajar';
    protected static ?string $recordTitleAttribute = 'tanggal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('penugasan_mengajar_id')->label('Penugasan Mengajar')
                ->options(
                    PenugasanMengajar::with(['pegawai.orang', 'mataPelajaran', 'rombel'])
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn ($p) => [
                            $p->id => sprintf(
                                '%s - %s (%s)',
                                $p->pegawai?->orang?->nama_lengkap ?? '-',
                                $p->mataPelajaran?->nama ?? '-',
                                $p->rombel?->nama ?? '-',
                            ),
                        ])
                )
                ->searchable()
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal')->required(),
            Select::make('jam_pelajaran_id')->label('Jam Pelajaran (kosongkan = harian TK/SD)')
                ->relationship('jamPelajaran', 'nama')
                ->searchable()
                ->nullable(),
            Select::make('pegawai_id')->label('Pengajar Sebenarnya')
                ->options(
                    Pegawai::with('orang')
                        ->orderBy('nip_yayasan')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->orang?->nama_lengkap ?? $p->nip_yayasan])
                )
                ->searchable()
                ->nullable(),
            Textarea::make('materi')->label('Materi')->nullable(),
            Textarea::make('kegiatan')->label('Kegiatan')->nullable(),
            Textarea::make('catatan')->label('Catatan')->nullable(),
            Select::make('status')->label('Status')
                ->enum(['hadir' => 'Hadir', 'diganti' => 'Diganti', 'kosong' => 'Kosong'])
                ->default('hadir'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date()->sortable(),
                TextColumn::make('penugasanMengajar.mataPelajaran.nama')->label('Mata Pelajaran')->sortable()->searchable(),
                TextColumn::make('penugasanMengajar.rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('jamPelajaran.hari')->label('Hari')->sortable(),
                TextColumn::make('jamPelajaran.urutan')->label('Urutan')->sortable(),
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Pengajar')->searchable(),
                TextColumn::make('status')->label('Status')->sortable(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJurnalMengajar::route('/'),
            'create' => CreateJurnalMengajar::route('/create'),
            'edit' => EditJurnalMengajar::route('/{record}/edit'),
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

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'kosong' || $record->presensi()->count() === 0;
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->presensi()->count() > 0) {
            throw new \App\Exceptions\Akademik\JurnalMengajarException(
                'Jurnal sudah memiliki presensi siswa. Hapus presensi terlebih dahulu.'
            );
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['penugasanMengajar.mataPelajaran', 'penugasanMengajar.rombel', 'jamPelajaran', 'pegawai.orang']);
    }
}
