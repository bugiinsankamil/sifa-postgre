<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\PembayaranGatewayLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use BackedEnum;
use UnitEnum;

class PembayaranGatewayLogResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = PembayaranGatewayLog::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Log Gateway';
    protected static ?string $modelLabel = 'log gateway';
    protected static ?string $pluralModelLabel = 'log gateway';
    protected static ?string $slug = 'keuangan/log-gateway';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data log')->schema([
                TextInput::make('penyedia')->disabled(),
                TextInput::make('referensi')->disabled(),
                TextInput::make('status_proses')->disabled(),
                Textarea::make('payload')->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('penyedia'),
                TextColumn::make('referensi'),
                TextColumn::make('payload')->label('Payload')->format(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v),
                TextColumn::make('status_proses')->badge(),
                TextColumn::make('diterima_pada')->datetime('d/m/Y H:i')->nullable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListPembayaranGatewayLog::route('/'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewPembayaranGatewayLog::route('/{record}'),
        ];
    }
}

