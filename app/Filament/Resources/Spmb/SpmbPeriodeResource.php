<?php

namespace App\Filament\Resources\Spmb;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Spmb\Pages\CreateSpmbPeriode;
use App\Filament\Resources\Spmb\Pages\EditSpmbPeriode;
use App\Filament\Resources\Spmb\Pages\ListSpmbPeriode;
use App\Filament\Resources\Spmb\RelationManagers\JalurRelationManager;
use App\Filament\Resources\Spmb\RelationManagers\PersyaratanRelationManager;
use App\Filament\Resources\Spmb\RelationManagers\TahapRelationManager;
use App\Models\Cabang;
use App\Models\Sekolah;
use App\Models\SpmbPeriode;
use App\Models\TahunAjaran;
use App\Exceptions\Spmb\TransisiTidakValid;
use App\Services\Spmb\AlurSeleksiSpmb;
use App\Services\Spmb\PengumumanHasilSpmb;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class SpmbPeriodeResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SpmbPeriode::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'SPMB';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Periode SPMB';

    protected static ?string $modelLabel = 'periode SPMB';

    protected static ?string $pluralModelLabel = 'periode SPMB';

    protected static ?string $slug = 'spmb/periode';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periode pendaftaran')->columns(2)->schema([
                Select::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('sekolah_id', null))
                    ->required()
                    ->disabledOn('edit'),
                Select::make('sekolah_id')
                    ->label('Sekolah (jenjang)')
                    ->options(fn (Get $get) => Sekolah::query()->where('cabang_id', $get('cabang_id'))->orderBy('nama')->pluck('nama', 'id'))
                    ->required()
                    ->disabledOn('edit'),
                Select::make('tahun_ajaran_id')
                    ->label('Tahun ajaran')
                    ->options(fn () => TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('nama')->label('Nama periode')->required()->maxLength(255),
                TextInput::make('gelombang')
                    ->numeric()->minValue(1)->default(1)->required()
                    ->unique(
                        table: 'spmb_periode',
                        column: 'gelombang',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('sekolah_id', $get('sekolah_id'))
                            ->where('tahun_ajaran_id', $get('tahun_ajaran_id')),
                    ),
                TextInput::make('kuota')->numeric()->minValue(0)->required(),
                DatePicker::make('tanggal_buka')->label('Tanggal buka')->required(),
                DatePicker::make('tanggal_tutup')->label('Tanggal tutup')->required()->afterOrEqual('tanggal_buka'),
                TextInput::make('biaya_pendaftaran')->label('Biaya pendaftaran')->numeric()->prefix('Rp')->default(0),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'selesai' => 'Selesai'])
                    ->default('draft')
                    ->required()
                    ->helperText('Formulir publik hanya menerima pendaftaran bila status Dibuka dan hari ini berada dalam rentang tanggal.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['cabang', 'sekolah'])
                ->withCount([
                    'pendaftar',
                    'pendaftar as diterima_count' => fn (Builder $q) => $q->whereIn('status', ['lulus', 'daftar_ulang', 'menjadi_siswa']),
                ]))
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('sekolah.nama')->label('Sekolah'),
                TextColumn::make('nama')->label('Periode')->searchable(),
                TextColumn::make('gelombang')->label('Gel.'),
                TextColumn::make('tanggal_buka')->label('Buka')->date('d/m/Y'),
                TextColumn::make('tanggal_tutup')->label('Tutup')->date('d/m/Y'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'dibuka' => 'success',
                    'ditutup' => 'warning',
                    'selesai' => 'info',
                    default => 'gray',
                }),
                TextColumn::make('pendaftar_count')->label('Pendaftar'),
                TextColumn::make('diterima_count')->label('Diterima')
                    ->description(fn (SpmbPeriode $record) => 'kuota '.$record->kuota),
                TextColumn::make('hasil_diumumkan_pada')->label('Diumumkan')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => blank($state) ? 'Belum' : 'Sudah')
                    ->color(fn ($state): string => blank($state) ? 'gray' : 'success')
                    ->description(fn (SpmbPeriode $record): ?string => $record->hasil_diumumkan_pada?->format('d/m/Y H:i')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('umumkanHasil')
                    ->label('Umumkan hasil')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->visible(fn (SpmbPeriode $record): bool => $record->hasil_diumumkan_pada === null
                        && app(CabangKonteks::class)->punyaPeran(['sysadmin', 'yayasan', 'direktur', 'kepala_cabang']))
                    ->requiresConfirmation()
                    ->modalHeading('Umumkan hasil seleksi?')
                    ->modalDescription(function (SpmbPeriode $record): string {
                        $r = PengumumanHasilSpmb::ringkasan($record);

                        return "Hasil akan terlihat di halaman cek status. Pesan akan diantrekan untuk {$r['lulus']} pendaftar lulus, {$r['cadangan']} cadangan, dan {$r['tidak_lulus']} tidak lulus. Tindakan ini tidak dapat dibatalkan.";
                    })
                    ->action(function (SpmbPeriode $record) {
                        try {
                            $jumlah = app(PengumumanHasilSpmb::class)->umumkan($record, auth()->id());
                        } catch (TransisiTidakValid $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title("Hasil diumumkan; {$jumlah} pesan diantrekan")
                            ->body('Pendaftar tanpa kontak atau templat aktif tidak memperoleh pesan.')
                            ->success()
                            ->send();
                    }),
                Action::make('peringkat')
                    ->label('Hitung peringkat')
                    ->icon('heroicon-o-trophy')
                    ->requiresConfirmation()
                    ->modalDescription('Peringkat dihitung ulang dari skor hasil seleksi yang sudah ditetapkan.')
                    ->action(function (SpmbPeriode $record) {
                        $jumlah = app(AlurSeleksiSpmb::class)->hitungPeringkat($record->id);

                        Notification::make()->title("Peringkat dihitung untuk {$jumlah} pendaftar")->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            JalurRelationManager::class,
            PersyaratanRelationManager::class,
            TahapRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpmbPeriode::route('/'),
            'create' => CreateSpmbPeriode::route('/create'),
            'edit' => EditSpmbPeriode::route('/{record}/edit'),
        ];
    }
}
