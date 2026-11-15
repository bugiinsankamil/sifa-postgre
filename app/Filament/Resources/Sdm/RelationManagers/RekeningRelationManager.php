<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RekeningRelationManager extends RelationManager
{
    protected static string $relationship = 'rekening';
    protected static ?string $title = 'Rekening bank';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('bank_ref')->label('Bank')
                ->options(fn () => PegawaiResource::opsiReferensi('bank'))->required(),
            TextInput::make('nomor_rekening')->required()->label('Nomor rekening'),
            TextInput::make('atas_nama')->required(),
            Toggle::make('is_utama')->label('Rekening utama'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('atas_nama')
            ->columns([
                TextColumn::make('bank_ref')->label('Bank'),
                TextColumn::make('atas_nama'),
                IconColumn::make('is_utama')->label('Utama')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
