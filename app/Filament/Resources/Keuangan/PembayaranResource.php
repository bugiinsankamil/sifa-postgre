<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\Cabang;
use App\Models\EntitasAkuntansi;
use App\Models\Pembayaran;
use App\Models\RekeningKas;
use App\Models\Siswa;
use App\Models\SpmbPendaftar;
use App\Models\User;
use App\Services\Keuangan\PembayaranService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use BackedEnum;
use UnitEnum;

class PembayaranResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = Pembayaran::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?string $modelLabel = 'pembayaran';
    protected static ?string $pluralModelLabel = 'pembayaran';
    protected static ?string $slug = 'keuangan/pembayaran';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pembayaran')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('entitas_id')->label('Entitas Akuntansi')
                    ->options(fn () => EntitasAkuntansi::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('siswa_id')->label('Siswa')
                    ->options(fn () => Siswa::query()->orderBy('nis_yayasan')->pluck('nis_yayasan', 'id'))
                    ->nullable()->searchable(),
                Select::make('pendaftar_id')->label('Pendaftar SPMB')
                    ->options(fn () => SpmbPendaftar::query()->orderBy('nomor_pendaftaran')->pluck('nomor_pendaftaran', 'id'))
                    ->nullable()->searchable(),
                DatePicker::make('tanggal')->required()->default(today()),
                Select::make('metode')->label('Metode')
                    ->options([
                        'tunai' => 'Tunai', 'transfer' => 'Transfer', 'virtual_account' => 'Virtual Account',
                        'qris' => 'QRIS', 'gateway' => 'Gateway',
                    ])
                    ->required(),
                TextInput::make('nominal')->label('Nominal')->numeric()->required(),
                Select::make('rekening_id')->label('Rekening Kas')
                    ->options(fn () => RekeningKas::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('referensi_eksternal')->label('Referensi Eksternal')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor'),
                TextColumn::make('siswa.nis_yayasan')->label('Siswa'),
                TextColumn::make('pendaftar.nomor_pendaftaran')->label('Pendaftar'),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('metode')->badge(),
                TextColumn::make('nominal')->money('idr'),
                TextColumn::make('rekening.nama')->label('Rekening'),
                TextColumn::make('status')->badge(),
                TextColumn::make('diterimaOleh.name')->label('Diterima Oleh'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'menunggu' => 'Menunggu', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'batal' => 'Batal',
                ]),
                SelectFilter::make('metode')->options([
                    'tunai' => 'Tunai', 'transfer' => 'Transfer', 'virtual_account' => 'Virtual Account',
                    'qris' => 'QRIS', 'gateway' => 'Gateway',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListPembayaran::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreatePembayaran::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditPembayaran::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewPembayaran::route('/{record}'),
        ];
    }

    public static function getRecordActions(\Illuminate\Database\Eloquent\Model $record): array
    {
        $actions = [];
        if ($record->status === 'menunggu') {
            $actions[] = \Filament\Actions\Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->color('success')
                ->action(fn (Pembayaran $record) => app(PembayaranService::class)->konfirmasi($record, auth()->user()))
                ->successNotification(Notification::make()->title('Pembayaran dikonfirmasi')->success());
        }
        if ($record->status === 'berhasil') {
            $actions[] = \Filament\Actions\Action::make('batalkan')
                ->label('Batalkan')
                ->color('danger')
                ->form([\Filament\Forms\Components\Textarea::make('alasan')->label('Alasan pembatalan')->required()->rows(2)])
                ->action(fn (array $data, Pembayaran $record) => app(PembayaranService::class)->batalkan($record, auth()->user(), $data['alasan']))
                ->successNotification(Notification::make()->title('Pembayaran dibatalkan')->success());
        }
        return $actions;
    }
}
