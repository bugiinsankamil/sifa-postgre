<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Models\SpmbTahapSeleksi;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TahapRelationManager extends RelationManager
{
    protected static string $relationship = 'tahap';

    protected static ?string $title = 'Tahap seleksi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama tahap')->required()->maxLength(255)
                ->helperText('Misalnya: Administrasi, Tes akademik, Observasi, Wawancara, Tes baca Al-Quran.'),
            TextInput::make('urutan')->numeric()->minValue(1)->required(),
            TextInput::make('bobot')->numeric()->minValue(0)->default(0)->required()
                ->helperText('Dipakai menghitung skor akhir (rata-rata berbobot). Isi 0 pada semua tahap untuk rata-rata biasa.'),
            TextInput::make('nilai_lulus')->label('Nilai minimal lulus (opsional)')->numeric()->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('urutan'),
                TextColumn::make('nama')->label('Tahap'),
                TextColumn::make('bobot'),
                TextColumn::make('nilai_lulus')->label('Minimal lulus'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->before(function (DeleteAction $action, SpmbTahapSeleksi $record) {
                    if (DB::table('spmb_nilai_seleksi')->where('tahap_id', $record->id)->exists()) {
                        Notification::make()->title('Tahap sudah memiliki nilai pendaftar')->danger()->send();
                        $action->halt();
                    }
                }),
            ]);
    }
}
