<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Models\Cabang;
use App\Models\Jabatan;
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
use Illuminate\Support\Facades\DB;

class PenugasanRelationManager extends RelationManager
{
    protected static string $relationship = 'penugasan';
    protected static ?string $title = 'Penugasan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cabang_id')->label('Cabang')
                ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))->required(),
            Select::make('sekolah_id')->label('Sekolah')
                ->options(fn (callable $get) => DB::table('sekolah')->where('cabang_id', $get('cabang_id'))->pluck('nama', 'id'))
                ->nullable(),
            Select::make('unit_kerja_id')->label('Unit kerja')
                ->options(fn () => DB::table('unit_kerja')->pluck('nama', 'id'))->nullable(),
            Select::make('jabatan_id')->label('Jabatan')
                ->options(fn () => Jabatan::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))->required(),
            DatePicker::make('tmt')->label('TMT')->required(),
            DatePicker::make('tanggal_akhir')->nullable()->afterOrEqual('tmt'),
            TextInput::make('no_sk')->label('No. SK'),
            Toggle::make('is_utama')->label('Penugasan utama'),
            Select::make('status')->options(['aktif' => 'Aktif', 'selesai' => 'Selesai'])->default('aktif')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('jabatan.nama')->label('Jabatan'),
                TextColumn::make('tmt')->label('TMT')->date('d/m/Y'),
                TextColumn::make('tanggal_akhir')->date('d/m/Y')->placeholder('Berjalan'),
                IconColumn::make('is_utama')->label('Utama')->boolean(),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
