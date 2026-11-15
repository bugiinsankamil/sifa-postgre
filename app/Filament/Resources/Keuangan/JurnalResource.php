<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Filament\Resources\Keuangan\Pages\CreateJurnal;
use App\Filament\Resources\Keuangan\Pages\EditJurnal;
use App\Filament\Resources\Keuangan\Pages\ListJurnal;
use App\Filament\Resources\Keuangan\Pages\ViewJurnal;
use App\Models\EntitasAkuntansi;
use App\Models\Jurnal;
use App\Services\Keuangan\JurnalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class JurnalResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = Jurnal::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Jurnal';
    protected static ?string $modelLabel = 'jurnal';
    protected static ?string $pluralModelLabel = 'jurnal';
    protected static ?string $slug = 'keuangan/jurnal';
    protected static ?string $recordTitleAttribute = 'nomor';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data jurnal')->columns(2)->schema([
                Select::make('entitas_id')->label('Entitas')
                    ->options(fn () => EntitasAkuntansi::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                DatePicker::make('tanggal')->required()->default(today()),
                Textarea::make('keterangan')->required()->columnSpan(2)->rows(2),
            ]),
            Section::make('Rincian jurnal')->schema([
                Repeater::make('baris')
                    ->relationship()
                    ->columns(4)
                    ->schema([
                        Select::make('akun_id')->label('Akun')
                            ->options(fn () => \App\Models\Akun::query()->where('is_header', false)->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                            ->required()->searchable(),
                        TextInput::make('debit')->numeric()->default(0)->minValue(0),
                        TextInput::make('kredit')->numeric()->default(0)->minValue(0),
                        TextInput::make('keterangan')->maxLength(255),
                    ])
                    ->defaultItems(2)
                    ->minItems(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')->searchable()->sortable(),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('entitas.nama')->label('Entitas'),
                TextColumn::make('keterangan')->limit(50),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'draft' => 'gray',
                    'posted' => 'success',
                    'void' => 'danger',
                    default => 'gray',
                }),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'posted' => 'Posted',
                    'void' => 'Void',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJurnal::route('/'),
            'create' => CreateJurnal::route('/create'),
            'edit' => EditJurnal::route('/{record}/edit'),
            'view' => ViewJurnal::route('/{record}'),
        ];
    }
}
