<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreatePegawai;
use App\Filament\Resources\Sdm\Pages\EditPegawai;
use App\Filament\Resources\Sdm\Pages\ListPegawai;
use App\Filament\Resources\Sdm\Pages\ViewPegawai;
use App\Models\Pegawai;
use App\Services\Sdm\StatusKepegawaianService;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PegawaiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = Pegawai::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Pegawai';
    protected static ?string $modelLabel = 'pegawai';
    protected static ?string $pluralModelLabel = 'pegawai';
    protected static ?string $slug = 'sdm/pegawai';
    protected static ?string $recordTitleAttribute = 'nip_yayasan';

    /** Opsi referensi (dipakai juga resource SDM lain). */
    public static function opsiReferensi(string $kategori): array
    {
        return DB::table('referensi')->where('kategori', $kategori)
            ->where('is_aktif', true)->orderBy('urutan')->pluck('label', 'kode')->all();
    }

    /** Pegawai dibatasi ke cabang penugasannya (pegawai tidak punya cabang_id sendiri). */
    public static function getEloquentQuery(): Builder
    {
        $ids = app(CabangKonteks::class)->cabangDiizinkan();

        return parent::getEloquentQuery()
            ->with(['orang', 'penugasanUtama.jabatan'])
            ->when($ids !== null, fn (Builder $q) => $q->whereHas('penugasan', fn ($p) => $p->whereIn('cabang_id', $ids)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas pegawai')->columns(2)->schema([
                TextInput::make('nip_yayasan')->label('NIP Yayasan')->required()->maxLength(30)
                    ->unique(ignoreRecord: true),
                Select::make('tipe')->options([
                    'guru' => 'Guru', 'tendik' => 'Tenaga kependidikan',
                    'staf' => 'Staf', 'pimpinan' => 'Pimpinan',
                ])->required(),
                Select::make('status_kepegawaian_ref')->label('Status kepegawaian')
                    ->options(fn () => static::opsiReferensi('status_kepegawaian'))->required(),
                Select::make('status')->options([
                    'aktif' => 'Aktif', 'cuti' => 'Cuti', 'nonaktif' => 'Nonaktif', 'resign' => 'Resign',
                ])->required()->default('aktif'),
                DatePicker::make('tanggal_masuk')->required(),
                DatePicker::make('tanggal_keluar')->nullable()->afterOrEqual('tanggal_masuk'),
                TextInput::make('alasan_keluar')->nullable()->columnSpan(2),
            ]),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nip_yayasan')->label('NIP')->searchable()->sortable(),
                TextColumn::make('orang.nama_lengkap')->label('Nama')->searchable()->sortable(),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('status_kepegawaian_ref')->label('Status kepegawaian'),
                TextColumn::make('tanggal_masuk')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'aktif' => 'success', 'cuti' => 'warning',
                    'nonaktif' => 'gray', 'resign' => 'danger', default => 'gray',
                }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('tipe')->options([
                    'guru' => 'Guru', 'tendik' => 'Tenaga kependidikan',
                    'staf' => 'Staf', 'pimpinan' => 'Pimpinan',
                ]),
                SelectFilter::make('status')->options([
                    'aktif' => 'Aktif', 'cuti' => 'Cuti', 'nonaktif' => 'Nonaktif', 'resign' => 'Resign',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('ubah_status')
                    ->label('Ubah status kepegawaian')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => static::canUbahStatus())
                    ->schema([
                        Select::make('status_kepegawaian_ref')->label('Status baru')
                            ->options(fn () => static::opsiReferensi('status_kepegawaian'))->required(),
                        DatePicker::make('tmt')->label('TMT')->required()->default(today()),
                        TextInput::make('no_sk')->label('No. SK'),
                        Textarea::make('keterangan')->rows(2),
                    ])
                    ->action(function (Pegawai $record, array $data) {
                        try {
                            app(StatusKepegawaianService::class)->ubahStatus(
                                $record, $data['status_kepegawaian_ref'], $data['tmt'],
                                $data['no_sk'] ?? null, $data['keterangan'] ?? null,
                            );
                            Notification::make()->title('Status kepegawaian diperbarui')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal mengubah status')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Sdm\RelationManagers\PenugasanRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\PendidikanRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\KeluargaRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\RekeningRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\KontrakRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\RiwayatStatusRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\SertifikasiRelationManager::class,
            \App\Filament\Resources\Sdm\RelationManagers\ShiftRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPegawai::route('/'),
            'create' => CreatePegawai::route('/create'),
            'edit' => EditPegawai::route('/{record}/edit'),
            'view' => ViewPegawai::route('/{record}'),
        ];
    }
}
