<?php

namespace App\Filament\Resources\Arsip;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Arsip\Pages\CreateSuratMasuk;
use App\Filament\Resources\Arsip\Pages\EditSuratMasuk;
use App\Filament\Resources\Arsip\Pages\ListSuratMasuk;
use App\Models\Cabang;
use App\Models\SuratMasuk;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SuratMasukResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SuratMasuk::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static string|UnitEnum|null $navigationGroup = 'Arsip';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Surat Masuk';
    protected static ?string $modelLabel = 'surat masuk';
    protected static ?string $pluralModelLabel = 'surat masuk';
    protected static ?string $slug = 'arsip/surat-masuk';
    protected static ?string $recordTitleAttribute = 'nomor_agenda';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data surat masuk')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                TextInput::make('nomor_agenda')->required()->maxLength(50),
                TextInput::make('nomor_surat')->maxLength(100),
                DatePicker::make('tanggal_surat'),
                DatePicker::make('tanggal_terima')->default(today())->required(),
                TextInput::make('pengirim')->required()->maxLength(255),
                TextInput::make('perihal')->required()->maxLength(255),
                Select::make('sifat')->options([
                    'biasa' => 'Biasa', 'penting' => 'Penting', 'rahasia' => 'Rahasia',
                ])->default('biasa')->required(),
                Select::make('klasifikasi_id')->label('Klasifikasi')
                    ->options(fn () => \App\Models\KlasifikasiArsip::query()->orderBy('kode')->pluck('nama', 'id'))->nullable(),
                Select::make('status')->options([
                    'diterima' => 'Diterima', 'didisposisikan' => 'Didisposisikan',
                    'diproses' => 'Diproses', 'selesai' => 'Selesai', 'diarsipkan' => 'Diarsipkan',
                ])->default('diterima')->required(),
                Textarea::make('ringkasan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_agenda')->label('Agenda')->searchable(),
                TextColumn::make('nomor_surat')->label('No. Surat')->searchable(),
                TextColumn::make('tanggal_terima')->date('d/m/Y')->sortable(),
                TextColumn::make('pengirim')->searchable(),
                TextColumn::make('perihal')->limit(40),
                TextColumn::make('sifat')->badge()->color(fn (string $state) => match ($state) {
                    'rahasia' => 'danger', 'penting' => 'warning', default => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'selesai' => 'success', 'diarsipkan' => 'info', default => 'gray',
                }),
            ])
            ->defaultSort('tanggal_terima', 'desc')
            ->filters([
                SelectFilter::make('sifat')->options([
                    'biasa' => 'Biasa', 'penting' => 'Penting', 'rahasia' => 'Rahasia',
                ]),
                SelectFilter::make('status')->options([
                    'diterima' => 'Diterima', 'didisposisikan' => 'Didisposisikan',
                    'diproses' => 'Diproses', 'selesai' => 'Selesai', 'diarsipkan' => 'Diarsipkan',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratMasuk::route('/'),
            'create' => CreateSuratMasuk::route('/create'),
            'edit' => EditSuratMasuk::route('/{record}/edit'),
        ];
    }
}
