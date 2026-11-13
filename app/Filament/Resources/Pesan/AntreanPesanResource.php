<?php

namespace App\Filament\Resources\Pesan;

use App\Filament\Concerns\AksesPesan;
use App\Filament\Resources\Pesan\Pages\ListAntreanPesan;
use App\Models\AntreanPesan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AntreanPesanResource extends Resource
{
    use AksesPesan;

    protected static ?string $model = AntreanPesan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Pesan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Antrean pesan';

    protected static ?string $modelLabel = 'pesan';

    protected static ?string $pluralModelLabel = 'antrean pesan';

    protected static ?string $slug = 'pesan/antrean';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('templat'))
            ->columns([
                TextColumn::make('created_at')->label('Dibuat')->since()->sortable(),
                TextColumn::make('kanal')->badge(),
                TextColumn::make('tujuan')->label('Tujuan')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '-' : (str_contains($state, '@')
                        ? $state
                        : str_repeat('*', max(strlen($state) - 4, 0)).substr($state, -4))),
                TextColumn::make('templat.kode')->label('Templat')->placeholder('(langsung)'),
                TextColumn::make('isi')->limit(50),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'terkirim' => 'success',
                    'antri' => 'warning',
                    'diproses' => 'info',
                    'gagal' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('percobaan')->label('Percobaan'),
                TextColumn::make('dijadwalkan_pada')->label('Dijadwalkan')->dateTime('d/m/Y H:i'),
                TextColumn::make('galat')->limit(40)->tooltip(fn (AntreanPesan $record) => $record->galat),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'antri' => 'Antri', 'diproses' => 'Diproses', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan',
                ]),
                SelectFilter::make('kanal')->options(['whatsapp' => 'WhatsApp', 'email' => 'Email']),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat isi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Isi pesan')
                    ->modalContent(fn (AntreanPesan $record) => new HtmlString('<div style="white-space:pre-wrap">'.e($record->isi).'</div>'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('kirimUlang')
                    ->label('Kirim ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (AntreanPesan $record) => $record->status === 'gagal')
                    ->requiresConfirmation()
                    ->action(fn (AntreanPesan $record) => $record->update([
                        'status' => 'antri', 'percobaan' => 0, 'galat' => null, 'dijadwalkan_pada' => now(),
                    ])),
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AntreanPesan $record) => $record->status === 'antri')
                    ->requiresConfirmation()
                    ->action(fn (AntreanPesan $record) => $record->update(['status' => 'dibatalkan'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAntreanPesan::route('/')];
    }
}
