<?php

namespace App\Filament\Resources\Inventaris;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Inventaris\Pages\CreateAsetUnit;
use App\Filament\Resources\Inventaris\Pages\EditAsetUnit;
use App\Filament\Resources\Inventaris\Pages\ListAsetUnit;
use App\Models\AsetUnit;
use App\Models\Barang;
use App\Models\Cabang;
use App\Models\Pegawai;
use App\Models\Ruang;
use App\Support\CabangKonteks;
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

class AsetUnitResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = AsetUnit::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static string|UnitEnum|null $navigationGroup = 'Inventaris';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Aset Tetap';
    protected static ?string $modelLabel = 'aset';
    protected static ?string $pluralModelLabel = 'aset tetap';
    protected static ?string $slug = 'inventaris/aset-unit';
    protected static ?string $recordTitleAttribute = 'kode_aset';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data aset')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                Select::make('barang_id')->label('Barang')
                    ->options(fn () => Barang::query()->where('jenis', 'aset_tetap')->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->searchable(),
                TextInput::make('kode_aset')->label('Kode Aset')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('nomor_seri')->maxLength(100),
                DatePicker::make('tanggal_perolehan')->required()->default(today()),
                TextInput::make('harga_perolehan')->numeric()->required()->prefix('Rp'),
                Select::make('sumber_dana_ref')->label('Sumber Dana')->options([
                    'yayasan' => 'Yayasan',
                    'bos' => 'BOS',
                    'hibah' => 'Hibah',
                    'wakaf' => 'Wakaf',
                    'lainnya' => 'Lainnya',
                ])->required(),
                TextInput::make('nilai_residu')->numeric()->default(0)->prefix('Rp'),
                TextInput::make('masa_manfaat_tahun')->numeric()->minValue(1)->label('Masa Manfaat (tahun)'),
                Select::make('kondisi')->options([
                    'baik' => 'Baik',
                    'rusak_ringan' => 'Rusak Ringan',
                    'rusak_berat' => 'Rusak Berat',
                ])->default('baik')->required(),


                Select::make('ruang_id')->label('Ruang')
                    ->options(fn () => Ruang::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('penanggung_jawab_id')->label('Penanggung Jawab')
                    ->options(fn () => Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->nullable()->searchable(),
                TextInput::make('kode_qr')->label('Kode QR')->required()->maxLength(100)->unique(ignoreRecord: true),
                Select::make('status')->options([
                    'aktif' => 'Aktif',
                    'dipinjam' => 'Dipinjam',
                    'perbaikan' => 'Perbaikan',
                    'dihapus' => 'Dihapus',
                ])->default('aktif')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_aset')->searchable()->sortable(),
                TextColumn::make('barang.nama')->label('Barang')->searchable(),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('ruang.nama')->label('Ruang'),
                TextColumn::make('tanggal_perolehan')->date('d/m/Y')->sortable(),
                TextColumn::make('harga_perolehan')->money('IDR')->sortable(),
                TextColumn::make('kondisi')->badge()->color(fn (string $state) => match ($state) {
                    'baik' => 'success',
                    'rusak_ringan' => 'warning',
                    'rusak_berat' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'aktif' => 'success',
                    'dipinjam' => 'warning',
                    'perbaikan' => 'info',
                    'dihapus' => 'danger',
                    default => 'gray',
                }),
            ])
            ->defaultSort('kode_aset')
            ->filters([
                SelectFilter::make('kondisi')->options([
                    'baik' => 'Baik',
                    'rusak_ringan' => 'Rusak Ringan',
                    'rusak_berat' => 'Rusak Berat',
                ]),
                SelectFilter::make('status')->options([
                    'aktif' => 'Aktif',
                    'dipinjam' => 'Dipinjam',
                    'perbaikan' => 'Perbaikan',
                    'dihapus' => 'Dihapus',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAsetUnit::route('/'),
            'create' => CreateAsetUnit::route('/create'),
            'edit' => EditAsetUnit::route('/{record}/edit'),
        ];
    }
}
