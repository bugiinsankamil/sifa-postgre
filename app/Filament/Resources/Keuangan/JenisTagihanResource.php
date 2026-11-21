<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\Akun;
use App\Models\Jenjang;
use App\Models\JenisTagihan;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use BackedEnum;
use UnitEnum;

class JenisTagihanResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = JenisTagihan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Jenis Tagihan';
    protected static ?string $modelLabel = 'jenis tagihan';
    protected static ?string $pluralModelLabel = 'jenis tagihan';
    protected static ?string $slug = 'keuangan/jenis-tagihan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data jenis tagihan')->schema([
                TextInput::make('kode')->label('Kode')->required()->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required(),
                Select::make('tipe')->label('Tipe')
                    ->options([
                        'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan', 'sekali' => 'Sekali',
                        'uang_pangkal' => 'Uang Pangkal', 'seragam' => 'Seragam', 'lainnya' => 'Lainnya',
                    ])
                    ->required(),
                Select::make('akun_pendapatan_id')->label('Akun Pendapatan')
                    ->options(fn () => Akun::query()->where('is_header', false)->where('is_aktif', true)->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('jenjang_id')->label('Jenjang')
                    ->options(fn () => Jenjang::query()->orderBy('urutan')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Toggle::make('boleh_cicil')->label('Boleh Cicil'),
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
                TextColumn::make('akunPendapatan.nama')->label('Akun Pendapatan'),
                TextColumn::make('jenjang.nama')->label('Jenjang'),
                ToggleColumn::make('boleh_cicil')->label('Cicil'),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('tipe')->options([
                    'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan', 'sekali' => 'Sekali',
                    'uang_pangkal' => 'Uang Pangkal', 'seragam' => 'Seragam', 'lainnya' => 'Lainnya',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListJenisTagihan::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateJenisTagihan::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditJenisTagihan::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewJenisTagihan::route('/{record}'),
        ];
    }
}
