<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Concerns\AksesKeuangan;
use App\Models\Cabang;
use App\Models\EntitasAkuntansi;
use App\Models\JenisTagihan;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SpmbPendaftar;
use App\Models\Tagihan;
use App\Models\TarifTagihan;
use App\Models\TahunAjaran;
use App\Services\Keuangan\TagihanService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use BackedEnum;
use UnitEnum;

class TagihanResource extends Resource
{
    use AksesKeuangan;

    protected static ?string $model = Tagihan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt';
    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationLabel = 'Tagihan';
    protected static ?string $modelLabel = 'tagihan';
    protected static ?string $pluralModelLabel = 'tagihan';
    protected static ?string $slug = 'keuangan/tagihan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data tagihan')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('entitas_id')->label('Entitas Akuntansi')
                    ->options(fn () => EntitasAkuntansi::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('siswa_id')->label('Siswa')
                    ->options(fn () => Siswa::query()->orderBy('nis_yayasan')->pluck('nis_yayasan', 'id'))
                    ->nullable()->searchable(),
                Select::make('pendaftar_id')->label('Pendaftar SPMB')
                    ->options(fn () => SpmbPendaftar::query()->orderBy('nomor_pendaftaran')->pluck('nomor_pendaftaran', 'id'))
                    ->nullable()->searchable(),
                Select::make('tahun_ajaran_id')->label('Tahun Ajaran')
                    ->options(fn () => TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('jenis_tagihan_id')->label('Jenis Tagihan')
                    ->options(fn () => JenisTagihan::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                Select::make('tarif_tagihan_id')->label('Tarif')
                    ->options(fn () => TarifTagihan::query()->where('is_aktif', true)->orderBy('nominal')->pluck('nominal', 'id'))
                    ->required()->searchable(),
                DatePicker::make('tanggal_terbit')->required()->default(today()),
                DatePicker::make('jatuh_tempo')->required(),
                TextInput::make('periode_label')->label('Periode Label')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor'),
                TextColumn::make('siswa.nis_yayasan')->label('Siswa'),
                TextColumn::make('pendaftar.nomor_pendaftaran')->label('Pendaftar'),
                TextColumn::make('jenisTagihan.nama')->label('Jenis Tagihan'),
                TextColumn::make('tanggal_terbit')->date('d/m/Y'),
                TextColumn::make('jatuh_tempo')->date('d/m/Y'),
                TextColumn::make('total')->money('idr'),
                TextColumn::make('terbayar')->money('idr'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('tanggal_terbit', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'belum' => 'Belum Bayar', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas', 'batal' => 'Batal',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Keuangan\Pages\ListTagihan::route('/'),
            'create' => \App\Filament\Resources\Keuangan\Pages\CreateTagihan::route('/create'),
            'edit' => \App\Filament\Resources\Keuangan\Pages\EditTagihan::route('/{record}/edit'),
            'view' => \App\Filament\Resources\Keuangan\Pages\ViewTagihan::route('/{record}'),
        ];
    }
}
