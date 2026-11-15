<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreatePelatihan;
use App\Filament\Resources\Sdm\Pages\EditPelatihan;
use App\Filament\Resources\Sdm\Pages\ListPelatihan;
use App\Models\Cabang;
use App\Models\Pelatihan;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PelatihanResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = Pelatihan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Pelatihan';
    protected static ?string $modelLabel = 'pelatihan';
    protected static ?string $pluralModelLabel = 'pelatihan';
    protected static ?string $slug = 'sdm/pelatihan';
    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pelatihan')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang (kosong = yayasan)')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('judul')->required(),
                Select::make('jenis_ref')->label('Jenis')
                    ->options(fn () => PegawaiResource::opsiReferensi('jenis_pelatihan'))->required(),
                TextInput::make('penyelenggara'),
                DatePicker::make('tanggal_mulai')->required(),
                DatePicker::make('tanggal_selesai')->required()->afterOrEqual('tanggal_mulai'),
                TextInput::make('lokasi'),
                TextInput::make('biaya')->numeric()->minValue(0)->prefix('Rp'),
                Select::make('status')->options([
                    'direncanakan' => 'Direncanakan', 'berjalan' => 'Berjalan',
                    'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
                ])->default('direncanakan')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->searchable()->limit(40),
                TextColumn::make('cabang.nama')->label('Cabang')->placeholder('Yayasan'),
                TextColumn::make('jenis_ref')->label('Jenis'),
                TextColumn::make('tanggal_mulai')->date('d/m/Y')->sortable(),
                TextColumn::make('tanggal_selesai')->date('d/m/Y'),
                TextColumn::make('peserta_count')->counts('peserta')->label('Peserta'),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'berjalan' => 'info', 'selesai' => 'success',
                    'dibatalkan' => 'danger', default => 'gray',
                }),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'direncanakan' => 'Direncanakan', 'berjalan' => 'Berjalan',
                    'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [\App\Filament\Resources\Sdm\RelationManagers\PesertaPelatihanRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPelatihan::route('/'),
            'create' => CreatePelatihan::route('/create'),
            'edit' => EditPelatihan::route('/{record}/edit'),
        ];
    }
}
