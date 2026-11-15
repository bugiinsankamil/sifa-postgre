<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Models\PegawaiShift;
use App\Models\ShiftKerja;
use App\Services\Sdm\ShiftKerjaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftRelationManager extends RelationManager
{
    protected static string $relationship = 'shift';
    protected static ?string $title = 'Penugasan shift';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('shift_id')->label('Shift')
                ->options(fn () => ShiftKerja::query()->orderBy('nama')->pluck('nama', 'id'))->required(),
            DatePicker::make('tanggal_mulai')->required()->default(today()),
            DatePicker::make('tanggal_akhir')->afterOrEqual('tanggal_mulai'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('shift.nama')->label('Shift'),
                TextColumn::make('shift.jam_masuk')->label('Masuk'),
                TextColumn::make('shift.jam_pulang')->label('Pulang'),
                TextColumn::make('tanggal_mulai')->date('d/m/Y'),
                TextColumn::make('tanggal_akhir')->date('d/m/Y')->placeholder('Berjalan'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('akhiri')
                    ->label('Akhiri')
                    ->icon('heroicon-o-x-circle')
                    ->schema([
                        DatePicker::make('tanggal_akhir')->required()->default(today()),
                    ])
                    ->visible(fn (PegawaiShift $r) => $r->tanggal_akhir === null)
                    ->action(function (PegawaiShift $record, array $data) {
                        try {
                            app(ShiftKerjaService::class)->akhiri($record, $data['tanggal_akhir']);
                            Notification::make()->title('Penugasan shift diakhiri')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
