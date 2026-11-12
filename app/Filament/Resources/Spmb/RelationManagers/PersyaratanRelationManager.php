<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Models\SpmbPersyaratanBerkas;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PersyaratanRelationManager extends RelationManager
{
    protected static string $relationship = 'persyaratan';

    protected static ?string $title = 'Persyaratan berkas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama berkas')->required()->maxLength(255),
            Toggle::make('is_wajib')->label('Wajib')->default(true),
            TextInput::make('urutan')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('urutan'),
                TextColumn::make('nama')->label('Berkas'),
                IconColumn::make('is_wajib')->label('Wajib')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->before(function (DeleteAction $action, SpmbPersyaratanBerkas $record) {
                    if (DB::table('spmb_berkas')->where('persyaratan_id', $record->id)->exists()) {
                        Notification::make()->title('Persyaratan sudah memiliki berkas pendaftar')->body('Jadikan tidak wajib, jangan dihapus.')->danger()->send();
                        $action->halt();
                    }
                }),
            ]);
    }
}
