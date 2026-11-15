<?php

namespace App\Filament\Resources\Spmb;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Spmb\Pages\CreateSpmbKampanye;
use App\Filament\Resources\Spmb\Pages\EditSpmbKampanye;
use App\Filament\Resources\Spmb\Pages\ListSpmbKampanye;
use App\Models\Cabang;
use App\Models\SpmbKampanye;
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

class SpmbKampanyeResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SpmbKampanye::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static string|UnitEnum|null $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Kampanye';
    protected static ?string $modelLabel = 'kampanye';
    protected static ?string $pluralModelLabel = 'kampanye';
    protected static ?string $slug = 'spmb/kampanye';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kampanye')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                TextInput::make('nama')->required()->maxLength(255),
                Select::make('kanal_ref')->label('Kanal')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('referensi')
                        ->where('kategori', 'spmb_kanal_kampanye')->where('is_aktif', true)
                        ->orderBy('urutan')->pluck('label', 'kode'))->required(),
                DatePicker::make('tanggal_mulai')->required(),
                DatePicker::make('tanggal_akhir')->after('tanggal_mulai'),
                TextInput::make('anggaran')->numeric()->prefix('Rp'),
                Textarea::make('catatan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable(),
                TextColumn::make('kanal_ref')->badge(),
                TextColumn::make('tanggal_mulai')->date('d/m/Y')->sortable(),
                TextColumn::make('tanggal_akhir')->date('d/m/Y'),
                TextColumn::make('anggaran')->money('IDR'),
                TextColumn::make('kontak_count')->counts('kontak')->label('Kontak'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('kanal_ref')->label('Kanal')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('referensi')
                        ->where('kategori', 'spmb_kanal_kampanye')->where('is_aktif', true)
                        ->orderBy('urutan')->pluck('label', 'kode')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpmbKampanye::route('/'),
            'create' => CreateSpmbKampanye::route('/create'),
            'edit' => EditSpmbKampanye::route('/{record}/edit'),
        ];
    }
}
