<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\Siswa;
use App\Models\VirtualAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use BackedEnum;
use UnitEnum;

class VirtualAccountResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = VirtualAccount::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Virtual Account';
    protected static ?string $modelLabel = 'virtual account';
    protected static ?string $pluralModelLabel = 'virtual account';
    protected static ?string $slug = 'keuangan/virtual-account';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data virtual account')->schema([
                Select::make('siswa_id')->label('Siswa')
                    ->options(fn () => Siswa::query()->orderBy('nis_yayasan')->pluck('nis_yayasan', 'id'))
                    ->required()->searchable(),
                TextInput::make('bank_ref')->label('Bank')->required(),
                TextInput::make('nomor')->label('Nomor VA')->required()->unique(ignoreRecord: true),
                Select::make('status')->label('Status')
                    ->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'kedaluwarsa' => 'Kedaluwarsa'])
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('siswa.nis_yayasan')->label('Siswa (NIS)'),
                TextColumn::make('bank_ref')->label('Bank'),
                TextColumn::make('nomor')->label('Nomor VA'),
                TextColumn::make('status')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListVirtualAccount::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateVirtualAccount::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditVirtualAccount::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewVirtualAccount::route('/{record}'),
        ];
    }
}
