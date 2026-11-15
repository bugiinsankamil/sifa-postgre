<?php

namespace App\Filament\Resources\Inventaris;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Inventaris\Pages\CreatePeminjaman;
use App\Filament\Resources\Inventaris\Pages\EditPeminjaman;
use App\Filament\Resources\Inventaris\Pages\ListPeminjaman;
use App\Models\Cabang;
use App\Models\Orang;
use App\Models\Peminjaman;
use App\Models\UnitKerja;
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

class PeminjamanResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = Peminjaman::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|UnitEnum|null $navigationGroup = 'Inventaris';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Peminjaman Aset';
    protected static ?string $modelLabel = 'peminjaman';
    protected static ?string $pluralModelLabel = 'peminjaman';
    protected static ?string $slug = 'inventaris/peminjaman';
    protected static ?string $recordTitleAttribute = 'nomor';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data peminjaman')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                TextInput::make('nomor')->required()->maxLength(50)->unique(ignoreRecord: true),
                Select::make('peminjam_id')->label('Peminjam')
                    ->options(fn () => Orang::query()->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id'))
                    ->required()->searchable(),
                Select::make('unit_kerja_id')->label('Unit Kerja')
                    ->options(fn () => UnitKerja::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                DatePicker::make('tanggal_pinjam')->required()->default(today()),
                DatePicker::make('tanggal_rencana_kembali')->required(),
                DatePicker::make('tanggal_kembali'),
                Select::make('status')->options([
                    'diajukan' => 'Diajukan',
                    'disetujui' => 'Disetujui',
                    'dipinjam' => 'Dipinjam',
                    'kembali' => 'Kembali',
                    'terlambat' => 'Terlambat',
                    'hilang' => 'Hilang',
                ])->default('diajukan')->required(),
                Textarea::make('keperluan')->required()->columnSpan(2)->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')->searchable()->sortable(),
                TextColumn::make('peminjam.nama_lengkap')->label('Peminjam')->searchable(),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('tanggal_pinjam')->date('d/m/Y')->sortable(),
                TextColumn::make('tanggal_rencana_kembali')->date('d/m/Y')->sortable(),
                TextColumn::make('tanggal_kembali')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'diajukan' => 'gray',
                    'disetujui' => 'info',
                    'dipinjam' => 'warning',
                    'kembali' => 'success',
                    'terlambat' => 'danger',
                    'hilang' => 'danger',
                    default => 'gray',
                }),
            ])
            ->defaultSort('tanggal_pinjam', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'diajukan' => 'Diajukan',
                    'disetujui' => 'Disetujui',
                    'dipinjam' => 'Dipinjam',
                    'kembali' => 'Kembali',
                    'terlambat' => 'Terlambat',
                    'hilang' => 'Hilang',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeminjaman::route('/'),
            'create' => CreatePeminjaman::route('/create'),
            'edit' => EditPeminjaman::route('/{record}/edit'),
        ];
    }
}
