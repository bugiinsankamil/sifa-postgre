<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateKurikulumSekolah;
use App\Filament\Resources\Akademik\Pages\EditKurikulumSekolah;
use App\Filament\Resources\Akademik\Pages\ListKurikulumSekolah;
use App\Models\Kurikulum;
use App\Models\KurikulumSekolah;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Services\Akademik\KurikulumService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class KurikulumSekolahResource extends Resource
{
    protected static ?string $model = KurikulumSekolah::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Penerapan Kurikulum';
    protected static ?string $modelLabel = 'penerapan kurikulum';
    protected static ?string $pluralModelLabel = 'penerapan kurikulum';
    protected static ?string $slug = 'akademik/penerapan-kurikulum';
    protected static ?string $recordTitleAttribute = 'kurikulum';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data penerapan')->columns(2)->schema([
                Select::make('kurikulum_id')->label('Kurikulum')
                    ->options(fn () => Kurikulum::query()->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('sekolah_id')->label('Sekolah')
                    ->options(fn () => Sekolah::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tahun_ajaran_id')->label('Tahun Ajaran')
                    ->options(fn () => TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tingkat_id')->label('Tingkat (kosongkan untuk semua)')
                    ->options(fn () => Tingkat::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kurikulum.kode')->label('Kurikulum')->searchable(),
                TextColumn::make('sekolah.nama')->label('Sekolah')->searchable(),
                TextColumn::make('tahun_ajaran.nama')->label('Tahun Ajaran')->searchable(),
                TextColumn::make('tingkat.kode')->label('Tingkat'),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ])
            ->defaultSort(['sekolah_id', 'tahun_ajaran_id', 'kurikulum_id'], 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKurikulumSekolah::route('/'),
            'create' => CreateKurikulumSekolah::route('/create'),
            'edit' => EditKurikulumSekolah::route('/{record}/edit'),
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
        return false; // Nonaktifkan sebagai pengganti hapus.
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function handleRecordCreation(array $data): Model
    {
        $sekolah = Sekolah::findOrFail($data['sekolah_id']);
        $tahunAjaran = TahunAjaran::findOrFail($data['tahun_ajaran_id']);
        $kurikulum = Kurikulum::findOrFail($data['kurikulum_id']);
        $tingkat = !empty($data['tingkat_id']) ? Tingkat::findOrFail($data['tingkat_id']) : null;
        $user = auth()->user();

        app(KurikulumService::class)->terapkan($kurikulum, $sekolah, $tahunAjaran, $tingkat, $user);

        Notification::make()
            ->title('Kurikulum berhasil diterapkan.')
            ->success()
            ->send();

        // Kembalikan record yang baru dibuat (idemen: mungkin sudah ada)
        return KurikulumSekolah::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->where('sekolah_id', $sekolah->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where(function ($q) use ($tingkat) {
                if ($tingkat) {
                    $q->where('tingkat_id', $tingkat->id);
                } else {
                    $q->whereNull('tingkat_id');
                }
            })
            ->firstOrFail();
    }

    protected static function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var KurikulumSekolah $record */
        $record->update(['is_aktif' => $data['is_aktif'] ?? true]);

        Notification::make()
            ->title('Penerapan kurikulum diperbarui.')
            ->success()
            ->send();

        return $record->fresh();
    }
}
