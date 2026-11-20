<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\RekeningKas;
use App\Models\RekonsiliasiBank;
use App\Services\Keuangan\RekonsiliasiBankService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use BackedEnum;
use UnitEnum;

class RekonsiliasiBankResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = RekonsiliasiBank::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Rekonsiliasi Bank';
    protected static ?string $modelLabel = 'rekonsiliasi bank';
    protected static ?string $pluralModelLabel = 'rekonsiliasi bank';
    protected static ?string $slug = 'keuangan/rekonsiliasi-bank';
    protected static ?string $recordTitleAttribute = 'rekening.nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data rekonsiliasi')->columns(2)->schema([
                Select::make('rekening_id')->label('Rekening Bank')
                    ->options(fn () => RekeningKas::query()->where('tipe', 'bank')->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('tahun')->numeric()->required()->default(date('Y')),
                TextInput::make('bulan')->numeric()->required()->default(date('n'))->minValue(1)->maxValue(12),
                TextInput::make('saldo_bank')->label('Saldo Bank')->numeric()->required(),
                Toggle::make('status')->label('Selesai')->disabled(),
                TextInput::make('selisih')->label('Selisih')->numeric()->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rekening.nama')->label('Rekening'),
                TextColumn::make('tahun'),
                TextColumn::make('bulan'),
                TextColumn::make('saldo_bank')->money('idr'),
                TextColumn::make('saldo_buku')->money('idr'),
                TextColumn::make('selisih')->money('idr'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tahun', 'desc')
            ->filters([
                Select::make('status')->options(['belum' => 'Belum', 'selesai' => 'Selesai']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListRekonsiliasiBank::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateRekonsiliasiBank::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditRekonsiliasiBank::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewRekonsiliasiBank::route('/{record}'),
        ];
    }

    public static function getRecordActions(\Illuminate\Database\Eloquent\Model $record): array
    {
        $actions = [];
        if ($record->status === 'belum') {
            $actions[] = Action::make('selesaikan')
                ->label('Selesaikan')
                ->form([
                    \Filament\Forms\Components\TextInput::make('saldo_bank')->label('Saldo Bank menurut rekaman bank')->numeric()->required(),
                ])
                ->action(fn (array $data, \Illuminate\Database\Eloquent\Model $record) => app(RekonsiliasiBankService::class)->selesaikan($record, auth()->id()))
                ->successNotification(Notification::make()->title('Rekonsiliasi selesai')->success());
        }
        return $actions;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->status === 'belum';
    }
}
