<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PendidikanRelationManager extends RelationManager
{
    protected static string $relationship = 'pendidikan';
    protected static ?string $title = 'Riwayat pendidikan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenjang_pendidikan_ref')->label('Jenjang')
                ->options(fn () => PegawaiResource::opsiReferensi('pendidikan'))->required(),
            TextInput::make('institusi')->required(),
            TextInput::make('jurusan'),
            TextInput::make('tahun_lulus')->numeric()->minValue(1900)->maxValue(2100),
            TextInput::make('ipk')->numeric()->minValue(0)->maxValue(4)->step('0.01'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('institusi')
            ->columns([
                TextColumn::make('jenjang_pendidikan_ref')->label('Jenjang'),
                TextColumn::make('institusi'),
                TextColumn::make('jurusan'),
                TextColumn::make('tahun_lulus')->label('Lulus'),
                TextColumn::make('ipk')->label('IPK'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
