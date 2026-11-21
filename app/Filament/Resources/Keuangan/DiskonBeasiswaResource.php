<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\DiskonBeasiswa;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use BackedEnum;
use UnitEnum;

class DiskonBeasiswaResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = DiskonBeasiswa::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Diskon / Beasiswa';
    protected static ?string $modelLabel = 'diskon beasiswa';
    protected static ?string $pluralModelLabel = 'diskon beasiswa';
    protected static ?string $slug = 'keuangan/diskon-beasiswa';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data diskon')->schema([
                TextInput::make('kode')->label('Kode')->required()->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required(),
                Select::make('tipe')->label('Tipe')
                    ->options(['persen' => 'Persen', 'nominal' => 'Nominal'])
                    ->required(),
                TextInput::make('nilai')->label('Nilai')->numeric()->required(),
                TextInput::make('sumber_dana')->label('Sumber Dana')->nullable(),
                Textarea::make('kriteria')->label('Kriteria')->nullable()->rows(2),
                Toggle::make('is_aktif')->label('Aktif'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode'),
                TextColumn::make('nama'),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('nilai'),
                TextColumn::make('sumber_dana'),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListDiskonBeasiswa::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateDiskonBeasiswa::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditDiskonBeasiswa::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewDiskonBeasiswa::route('/{record}'),
        ];
    }
}