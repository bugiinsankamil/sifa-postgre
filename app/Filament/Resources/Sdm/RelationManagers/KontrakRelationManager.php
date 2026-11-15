<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use App\Models\KontrakKerja;
use App\Services\Sdm\KontrakKerjaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KontrakRelationManager extends RelationManager
{
    protected static string $relationship = 'kontrak';
    protected static ?string $title = 'Kontrak kerja';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nomor')->required()->unique(ignoreRecord: true),
            Select::make('jenis')->options(['pkwt' => 'PKWT', 'tetap' => 'Tetap', 'honor' => 'Honor'])->required(),
            DatePicker::make('tanggal_mulai')->required(),
            DatePicker::make('tanggal_selesai')->afterOrEqual('tanggal_mulai'),
            TextInput::make('gaji_pokok')->numeric()->minValue(0)->prefix('Rp'),
            Select::make('status')->options([
                'aktif' => 'Aktif', 'berakhir' => 'Berakhir',
                'diperpanjang' => 'Diperpanjang', 'dibatalkan' => 'Dibatalkan',
            ])->default('aktif')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nomor')
            ->columns([
                TextColumn::make('nomor')->searchable(),
                TextColumn::make('jenis')->badge(),
                TextColumn::make('tanggal_mulai')->date('d/m/Y'),
                TextColumn::make('tanggal_selesai')->date('d/m/Y')->placeholder('Tidak ditentukan'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make()->visible(fn (KontrakKerja $r) => $r->status === 'aktif'),
                Action::make('akhiri')
                    ->label('Akhiri')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (KontrakKerja $r) => $r->status === 'aktif' && PegawaiResource::canUbahStatus())
                    ->action(function (KontrakKerja $record) {
                        try {
                            app(KontrakKerjaService::class)->akhiri($record);
                            Notification::make()->title('Kontrak diakhiri')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
