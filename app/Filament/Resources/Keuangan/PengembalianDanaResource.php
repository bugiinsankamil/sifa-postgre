<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\Pembayaran;
use App\Models\PengembalianDana;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use BackedEnum;
use UnitEnum;

class PengembalianDanaResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = PengembalianDana::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-left';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'Pengembalian Dana';
    protected static ?string $modelLabel = 'pengembalian dana';
    protected static ?string $pluralModelLabel = 'pengembalian dana';
    protected static ?string $slug = 'keuangan/pengembalian-dana';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pengembalian dana')->schema([
                Select::make('pembayaran_id')->label('Pembayaran')
                    ->options(fn () => Pembayaran::query()->where('status', 'berhasil')->orderByDesc('tanggal')->pluck('nomor', 'id'))
                    ->required()->searchable(),
                TextInput::make('nominal')->label('Nominal')->numeric()->required(),
                TextInput::make('alasan')->label('Alasan')->required(),
                DatePicker::make('tanggal')->required()->default(today()),
                Select::make('status')->label('Status')
                    ->options(['diaju' => 'Diaju', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai'])
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pembayaran.nomor')->label('Pembayaran'),
                TextColumn::make('nominal')->money('idr'),
                TextColumn::make('alasan'),
                TextColumn::make('tanggal')->date('d/m/Y'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListPengembalianDana::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreatePengembalianDana::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditPengembalianDana::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewPengembalianDana::route('/{record}'),
        ];
    }
}
