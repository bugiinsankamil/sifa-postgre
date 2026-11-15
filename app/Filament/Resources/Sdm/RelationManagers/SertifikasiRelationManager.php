<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SertifikasiRelationManager extends RelationManager
{
    protected static string $relationship = 'sertifikasi';
    protected static ?string $title = 'Sertifikasi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenis_ref')->label('Jenis')
                ->options(fn () => PegawaiResource::opsiReferensi('jenis_sertifikasi'))->required(),
            TextInput::make('nomor'),
            TextInput::make('penerbit'),
            DatePicker::make('tanggal_terbit'),
            DatePicker::make('tanggal_kedaluwarsa')->afterOrEqual('tanggal_terbit'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nomor')
            ->columns([
                TextColumn::make('jenis_ref')->label('Jenis'),
                TextColumn::make('nomor'),
                TextColumn::make('penerbit'),
                TextColumn::make('tanggal_terbit')->date('d/m/Y'),
                TextColumn::make('tanggal_kedaluwarsa')->date('d/m/Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
