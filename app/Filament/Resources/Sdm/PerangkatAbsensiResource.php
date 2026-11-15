<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreatePerangkatAbsensi;
use App\Filament\Resources\Sdm\Pages\EditPerangkatAbsensi;
use App\Filament\Resources\Sdm\Pages\ListPerangkatAbsensi;
use App\Models\Cabang;
use App\Models\PerangkatAbsensi;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PerangkatAbsensiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = PerangkatAbsensi::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Perangkat Absensi';
    protected static ?string $modelLabel = 'perangkat absensi';
    protected static ?string $pluralModelLabel = 'perangkat absensi';
    protected static ?string $slug = 'sdm/perangkat-absensi';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data perangkat')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('nama')->required(),
                Select::make('tipe')->options([
                    'fingerprint' => 'Fingerprint', 'wajah' => 'Wajah', 'qr' => 'QR Code',
                ])->required(),
                TextInput::make('nomor_seri')->required()->unique(ignoreRecord: true),
                TextInput::make('alamat_ip')->label('Alamat IP'),
                Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])
                    ->default('aktif')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('nomor_seri')->searchable(),
                TextColumn::make('status')->badge()->color(fn (string $s) => $s === 'aktif' ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('tipe')->options([
                    'fingerprint' => 'Fingerprint', 'wajah' => 'Wajah', 'qr' => 'QR Code',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerangkatAbsensi::route('/'),
            'create' => CreatePerangkatAbsensi::route('/create'),
            'edit' => EditPerangkatAbsensi::route('/{record}/edit'),
        ];
    }
}
