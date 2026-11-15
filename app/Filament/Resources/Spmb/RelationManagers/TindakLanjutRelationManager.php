<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Models\SpmbTindakLanjut;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TindakLanjutRelationManager extends RelationManager
{
    protected static string $relationship = 'tindakLanjut';

    protected static ?string $title = 'Riwayat tindak lanjut';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal')->default(today())->required(),
            Select::make('kanal')->options([
                'whatsapp' => 'WhatsApp', 'telepon' => 'Telepon',
                'kunjungan' => 'Kunjungan', 'email' => 'Email',
            ])->required(),
            TextInput::make('hasil')->label('Hasil')->required()->maxLength(150),
            Textarea::make('catatan')->label('Catatan'),
            DatePicker::make('jadwal_berikutnya')->label('Jadwal berikutnya'),
            Select::make('status_setelah')->label('Status setelah follow-up')->options([
                'baru' => 'Baru', 'dihubungi' => 'Dihubungi', 'tertarik' => 'Tertarik',
                'mendaftar' => 'Mendaftar', 'tidak_lanjut' => 'Tidak lanjut',
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('kanal')->badge(),
                TextColumn::make('hasil')->limit(50),
                TextColumn::make('petugas.id')->label('Petugas'),
                TextColumn::make('jadwal_berikutnya')->date('d/m/Y'),
                TextColumn::make('status_setelah')->badge(),
            ])
            ->defaultSort('tanggal', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
