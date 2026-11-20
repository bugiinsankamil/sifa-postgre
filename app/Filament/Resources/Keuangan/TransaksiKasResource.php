<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\RekeningKas;
use App\Models\Akun;
use App\Models\TransaksiKas;
use App\Services\Keuangan\KasService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use BackedEnum;
use UnitEnum;

class TransaksiKasResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = TransaksiKas::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Transaksi Kas';
    protected static ?string $modelLabel = 'transaksi kas';
    protected static ?string $pluralModelLabel = 'transaksi kas';
    protected static ?string $slug = 'keuangan/transaksi-kas';
    protected static ?string $recordTitleAttribute = 'nominal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data transaksi')->columns(2)->schema([
                Select::make('rekening_id')->label('Rekening')
                    ->options(fn () => RekeningKas::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                DatePicker::make('tanggal')->required()->default(today()),
                Select::make('tipe')->label('Tipe')
                    ->options(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'transfer' => 'Transfer'])
                    ->required(),
                TextInput::make('nominal')->label('Nominal')->numeric()->required(),
                Select::make('akun_lawan_id')->label('Akun Lawan')
                    ->options(fn () => Akun::query()->where('is_header', false)->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('rekening_tujuan_id')->label('Rekening Tujuan (transfer)')
                    ->options(fn () => RekeningKas::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Textarea::make('keterangan')->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('nominal')->money('idr'),
                TextColumn::make('rekening.nama')->label('Rekening'),
                TextColumn::make('akunLawan.nama')->label('Akun Lawan'),
                TextColumn::make('rekeningTujuan.nama')->label('Tujuan'),
                TextColumn::make('keterangan')->limit(40),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('tipe')->options(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'transfer' => 'Transfer']),
                SelectFilter::make('status')->options(['posted' => 'Dicatat', 'batal' => 'Dibatalkan']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListTransaksiKas::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateTransaksiKas::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditTransaksiKas::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewTransaksiKas::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordActions(\Illuminate\Database\Eloquent\Model $record): array
    {
        $actions = [];
        if ($record->status === 'posted') {
            $actions[] = Action::make('batalkan')
                ->label('Batalkan')
                ->form([
                    \Filament\Forms\Components\Textarea::make('alasan')->label('Alasan pembatalan')->required()->rows(2),
                ])
                ->action(fn (array $data, \Illuminate\Database\Eloquent\Model $record) => app(KasService::class)->batalkan($record, auth()->id(), $data['alasan']))
                ->successNotification(\Filament\Notifications\Notification::make()->title('Transaksi dibatalkan')->success());
        }
        return $actions;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Histori transaksi tidak diedit; batalkan lewat aksi khusus
    }
}
