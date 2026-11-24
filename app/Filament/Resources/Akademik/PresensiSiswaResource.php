<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreatePresensiSiswa;
use App\Filament\Resources\Akademik\Pages\EditPresensiSiswa;
use App\Filament\Resources\Akademik\Pages\ListPresensiSiswa;
use App\Models\JurnalMengajar;
use App\Models\PresensiSiswa;
use App\Models\Rombel;
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

class PresensiSiswaResource extends Resource
{
    protected static ?string $model = PresensiSiswa::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-clipboard-document-list';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Presensi Siswa';
    protected static ?string $slug = 'akademik/presensi-siswa';
    protected static ?string $recordTitleAttribute = 'tanggal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rombel_id')->label('Rombel')
                ->relationship('rombel', 'nama')
                ->searchable()
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal')->required(),
            Select::make('jurnal_mengajar_id')->label('Jurnal Mengajar (kosongkan = harian TK/SD)')
                ->relationship('jurnalMengajar', 'tanggal')
                ->searchable()
                ->nullable(),
            Select::make('siswa_id')->label('Siswa')
                ->options(
                    Siswa::with('orang')
                        ->orderBy('nis_yayasan')
                        ->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->nis_yayasan.' - '.$s->orang?->nama_lengkap])
                )
                ->searchable()
                ->required(),
            Select::make('status')->label('Status')
                ->enum([
                    'hadir' => 'Hadir',
                    'sakit' => 'Sakit',
                    'izin' => 'Izin',
                    'terlambat' => 'Terlambat',
                    'alpa' => 'Alpa',
                ])
                ->default('hadir'),
            Textarea::make('keterangan')->label('Keterangan')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date()->sortable(),
                TextColumn::make('rombel.nama')->label('Rombel')->sortable()->searchable(),
                TextColumn::make('siswa.orang.nama_lengkap')->label('Siswa')->sortable()->searchable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('jurnalMengajar.tanggal')->label('Jurnal')->date()->toggleable(),
                TextColumn::make('dicatatOleh.orang.nama_lengkap')->label('Dicatat Oleh')->toggleable(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresensiSiswa::route('/'),
            'create' => CreatePresensiSiswa::route('/create'),
            'edit' => EditPresensiSiswa::route('/{record}/edit'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['rombel', 'siswa.orang', 'jurnalMengajar', 'dicatatOleh.orang']);
    }
}
