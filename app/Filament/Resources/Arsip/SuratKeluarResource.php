<?php

namespace App\Filament\Resources\Arsip;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Arsip\Pages\CreateSuratKeluar;
use App\Filament\Resources\Arsip\Pages\EditSuratKeluar;
use App\Filament\Resources\Arsip\Pages\ListSuratKeluar;
use App\Models\Cabang;
use App\Models\SuratKeluar;
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

class SuratKeluarResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SuratKeluar::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string|UnitEnum|null $navigationGroup = 'Arsip';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Surat Keluar';
    protected static ?string $modelLabel = 'surat keluar';
    protected static ?string $pluralModelLabel = 'surat keluar';
    protected static ?string $slug = 'arsip/surat-keluar';
    protected static ?string $recordTitleAttribute = 'nomor_surat';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data surat keluar')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                TextInput::make('nomor_surat')->label('Nomor surat (otomatis bila kosong)')->maxLength(100),
                DatePicker::make('tanggal_surat')->default(today())->required(),
                TextInput::make('tujuan')->required()->maxLength(255),
                TextInput::make('perihal')->required()->maxLength(255),
                Select::make('jenis')->options([
                    'biasa' => 'Biasa', 'edaran' => 'Edaran', 'sk' => 'SK',
                    'mou' => 'MoU', 'undangan' => 'Undangan', 'keterangan' => 'Keterangan',
                ])->required(),
                Select::make('klasifikasi_id')->label('Klasifikasi')
                    ->options(fn () => \App\Models\KlasifikasiArsip::query()->orderBy('kode')->pluck('nama', 'id'))->nullable(),
                Select::make('pembuat_id')->label('Pembuat')
                    ->options(fn () => \App\Models\Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->required()->searchable(),
                Select::make('penandatangan_id')->label('Penandatangan')
                    ->options(fn () => \App\Models\Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->nullable()->searchable(),
                Select::make('status')->options([
                    'draft' => 'Draft', 'review' => 'Review', 'disetujui' => 'Disetujui',
                    'terkirim' => 'Terkirim', 'diarsipkan' => 'Diarsipkan',
                ])->default('draft')->required(),
                DatePicker::make('tanggal_kirim'),
                Textarea::make('isi')->label('Isi surat')->required()->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_surat')->label('Nomor')->searchable(),
                TextColumn::make('tanggal_surat')->date('d/m/Y')->sortable(),
                TextColumn::make('tujuan')->searchable(),
                TextColumn::make('perihal')->limit(40),
                TextColumn::make('jenis')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'terkirim' => 'success', 'disetujui' => 'info', 'review' => 'warning', default => 'gray',
                }),
            ])
            ->defaultSort('tanggal_surat', 'desc')
            ->filters([
                SelectFilter::make('jenis')->options([
                    'biasa' => 'Biasa', 'edaran' => 'Edaran', 'sk' => 'SK',
                    'mou' => 'MoU', 'undangan' => 'Undangan', 'keterangan' => 'Keterangan',
                ]),
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'review' => 'Review', 'disetujui' => 'Disetujui',
                    'terkirim' => 'Terkirim', 'diarsipkan' => 'Diarsipkan',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratKeluar::route('/'),
            'create' => CreateSuratKeluar::route('/create'),
            'edit' => EditSuratKeluar::route('/{record}/edit'),
        ];
    }
}
