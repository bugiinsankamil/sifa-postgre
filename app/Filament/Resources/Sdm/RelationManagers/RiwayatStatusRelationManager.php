<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Filament\Resources\Sdm\PegawaiResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RiwayatStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatStatus';
    protected static ?string $title = 'Riwayat status kepegawaian';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('status_kepegawaian_ref')->label('Status')
                    ->formatStateUsing(fn ($state) => DB::table('referensi')
                        ->where('kategori', 'status_kepegawaian')->where('kode', $state)->value('label') ?? $state),
                TextColumn::make('tmt')->label('TMT')->date('d/m/Y'),
                TextColumn::make('no_sk')->label('No. SK')->placeholder('-'),
                TextColumn::make('keterangan')->limit(50),
            ])
            ->defaultSort('tmt', 'desc');
    }
}
