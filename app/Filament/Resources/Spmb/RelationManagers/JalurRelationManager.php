<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Models\SpmbJalur;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class JalurRelationManager extends RelationManager
{
    protected static string $relationship = 'jalur';

    protected static ?string $title = 'Jalur pendaftaran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')
                ->required()->maxLength(255)
                ->unique(
                    table: 'spmb_jalur',
                    column: 'nama',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('periode_id', $this->getOwnerRecord()->id),
                ),
            TextInput::make('kuota')->numeric()->minValue(0),
            Toggle::make('is_aktif')->label('Aktif')->default(true),
            KeyValue::make('persyaratan')->label('Persyaratan khusus (opsional)')->keyLabel('Syarat')->valueLabel('Keterangan'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nama'),
                TextColumn::make('kuota'),
                IconColumn::make('is_aktif')->label('Aktif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->before(function (DeleteAction $action, SpmbJalur $record) {
                    if (DB::table('spmb_pendaftar')->where('jalur_id', $record->id)->exists()) {
                        Notification::make()->title('Jalur sudah dipakai pendaftar')->body('Nonaktifkan jalur ini, jangan dihapus.')->danger()->send();
                        $action->halt();
                    }
                }),
            ]);
    }
}
