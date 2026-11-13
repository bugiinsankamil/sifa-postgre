<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\SpmbBerkas;
use App\Services\Spmb\AlurSeleksiSpmb;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BerkasRelationManager extends RelationManager
{
    protected static string $relationship = 'berkas';

    protected static ?string $title = 'Berkas persyaratan';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with(['persyaratan', 'lampiran']))
            ->columns([
                TextColumn::make('persyaratan.nama')->label('Berkas'),
                TextColumn::make('status_verifikasi')->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'tidak_valid' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'valid' => 'Valid',
                        'tidak_valid' => 'Tidak valid',
                        default => 'Menunggu',
                    }),
                TextColumn::make('catatan')->limit(60),
                TextColumn::make('updated_at')->label('Diperbarui')->since(),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (SpmbBerkas $record) => $record->lampiran->isNotEmpty())
                    ->url(fn (SpmbBerkas $record) => route('spmb.admin.berkas', $record->lampiran->first()->id))
                    ->openUrlInNewTab(),
                Action::make('valid')
                    ->label('Valid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (SpmbBerkas $record) => $record->status_verifikasi !== 'valid')
                    ->action(fn (SpmbBerkas $record) => $this->tetapkan($record, 'valid', null)),
                Action::make('tidakValid')
                    ->label('Tidak valid')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (SpmbBerkas $record) => $record->status_verifikasi !== 'tidak_valid')
                    ->schema([Textarea::make('catatan')->label('Alasan (terlihat oleh pendaftar)')->required()])
                    ->action(fn (SpmbBerkas $record, array $data) => $this->tetapkan($record, 'tidak_valid', $data['catatan'])),
            ]);
    }

    private function tetapkan(SpmbBerkas $berkas, string $status, ?string $catatan): void
    {
        try {
            app(AlurSeleksiSpmb::class)->tetapkanBerkas($berkas, $status, $catatan, auth()->id());
        } catch (TransisiTidakValid $e) {
            Notification::make()->title('Tidak dapat diproses')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Status berkas diperbarui')->success()->send();
    }
}
