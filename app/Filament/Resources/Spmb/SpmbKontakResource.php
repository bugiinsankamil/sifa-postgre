<?php

namespace App\Filament\Resources\Spmb;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Spmb\Pages\CreateSpmbKontak;
use App\Filament\Resources\Spmb\Pages\EditSpmbKontak;
use App\Filament\Resources\Spmb\Pages\ListSpmbKontak;
use App\Filament\Resources\Spmb\RelationManagers\TindakLanjutRelationManager;
use App\Models\Cabang;
use App\Models\SpmbKontak;
use App\Models\Sekolah;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SpmbKontakResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SpmbKontak::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|UnitEnum|null $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Prospek (CRM)';
    protected static ?string $modelLabel = 'kontak prospek';
    protected static ?string $pluralModelLabel = 'kontak prospek';
    protected static ?string $slug = 'spmb/kontak';
    protected static ?string $recordTitleAttribute = 'nama_kontak';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kontak')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->live()->afterStateUpdated(fn (Set $set) => $set('sekolah_id', null))
                    ->required()->disabledOn('edit'),
                Select::make('sekolah_id')->label('Sekolah yang diminati')
                    ->options(fn (Get $get) => Sekolah::query()->where('cabang_id', $get('cabang_id'))->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable(),
                Select::make('tahun_ajaran_id')->label('Tahun ajaran')
                    ->options(fn () => \App\Models\TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->required(),
                TextInput::make('nama_kontak')->label('Nama kontak (orang tua)')->required()->maxLength(255),
                TextInput::make('nama_calon_siswa')->label('Nama calon siswa')->maxLength(255),
                TextInput::make('telepon')->label('Telepon')->required()->maxLength(30),
                TextInput::make('whatsapp')->label('WhatsApp')->maxLength(30),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                Select::make('status')->options([
                    'baru' => 'Baru', 'dihubungi' => 'Dihubungi', 'tertarik' => 'Tertarik',
                    'mendaftar' => 'Mendaftar', 'tidak_lanjut' => 'Tidak lanjut',
                ])->default('baru')->required(),
                Textarea::make('catatan')->label('Catatan')->columnSpan(2),
            ]),
            Section::make('Sumber dan kampanye')->columns(2)->schema([
                Select::make('sumber_ref')->label('Sumber')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('referensi')
                        ->where('kategori', 'spmb_sumber')->where('is_aktif', true)
                        ->orderBy('urutan')->pluck('label', 'kode'))->required(),
                Select::make('kampanye_id')->label('Kampanye')
                    ->options(fn () => \App\Models\SpmbKampanye::query()->orderBy('nama')->pluck('nama', 'id'))->nullable(),
                Select::make('petugas_id')->label('Petugas follow-up')
                    ->options(fn () => \App\Models\Pegawai::query()->orderBy('id')->pluck('id', 'id'))
                    ->nullable()->searchable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sekolah', 'kampanye']))
            ->columns([
                TextColumn::make('nama_kontak')->label('Kontak')->searchable(),
                TextColumn::make('nama_calon_siswa')->label('Calon siswa')->searchable(),
                TextColumn::make('telepon')->label('Telepon'),
                TextColumn::make('sekolah.nama')->label('Sekolah')->placeholder('-'),
                TextColumn::make('sumber_ref')->label('Sumber')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'mendaftar' => 'success', 'tertarik' => 'info', 'dihubungi' => 'warning',
                    'tidak_lanjut' => 'danger', default => 'gray',
                }),
                TextColumn::make('kampanye.nama')->label('Kampanye')->placeholder('-'),
                TextColumn::make('kontak_pertama_pada')->label('Kontak pertama')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'baru' => 'Baru', 'dihubungi' => 'Dihubungi', 'tertarik' => 'Tertarik',
                    'mendaftar' => 'Mendaftar', 'tidak_lanjut' => 'Tidak lanjut',
                ]),
                SelectFilter::make('sumber_ref')->label('Sumber')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('referensi')
                        ->where('kategori', 'spmb_sumber')->where('is_aktif', true)
                        ->orderBy('urutan')->pluck('label', 'kode')),
                SelectFilter::make('tahun_ajaran_id')->label('Tahun ajaran')
                    ->options(fn () => \App\Models\TahunAjaran::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id')),
            ]);
    }

    public static function getRelations(): array
    {
        return [TindakLanjutRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpmbKontak::route('/'),
            'create' => CreateSpmbKontak::route('/create'),
            'edit' => EditSpmbKontak::route('/{record}/edit'),
        ];
    }
}
