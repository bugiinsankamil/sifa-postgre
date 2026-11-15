<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeluargaRelationManager extends RelationManager
{
    protected static string $relationship = 'keluarga';
    protected static ?string $title = 'Keluarga / tanggungan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->required(),
            Select::make('hubungan_ref')->label('Hubungan')
                ->options(fn () => PegawaiResource::opsiReferensi('hubungan_keluarga'))->required(),
            DatePicker::make('tanggal_lahir'),
            Toggle::make('is_tanggungan')->label('Tanggungan'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nama'),
                TextColumn::make('hubungan_ref')->label('Hubungan'),
                TextColumn::make('tanggal_lahir')->date('d/m/Y'),
                IconColumn::make('is_tanggungan')->label('Tanggungan')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
