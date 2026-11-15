<?php

namespace App\Filament\Resources\Spmb;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Spmb\Pages\CreateSpmbKegiatan;
use App\Filament\Resources\Spmb\Pages\EditSpmbKegiatan;
use App\Filament\Resources\Spmb\Pages\ListSpmbKegiatan;
use App\Models\Cabang;
use App\Models\SpmbKegiatan;
use App\Models\SpmbPeriode;
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

class SpmbKegiatanResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SpmbKegiatan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Kegiatan';
    protected static ?string $modelLabel = 'kegiatan';
    protected static ?string $pluralModelLabel = 'kegiatan';
    protected static ?string $slug = 'spmb/kegiatan';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kegiatan')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->dapatDiakses()->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => app(CabangKonteks::class)->cabangTunggal())
                    ->required()->disabledOn('edit'),
                Select::make('periode_id')->label('Periode SPMB')
                    ->options(fn () => SpmbPeriode::query()->orderByDesc('id')->pluck('nama', 'id'))->nullable(),
                TextInput::make('nama')->required()->maxLength(255),
                Select::make('jenis')->options([
                    'open_house' => 'Open House', 'school_tour' => 'School Tour',
                    'webinar' => 'Webinar', 'lainnya' => 'Lainnya',
                ])->required(),
                DatePicker::make('tanggal')->required(),
                TextInput::make('lokasi')->maxLength(255),
                TextInput::make('kuota')->numeric()->minValue(1),
                Textarea::make('catatan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->searchable(),
                TextColumn::make('jenis')->badge(),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('lokasi')->placeholder('-'),
                TextColumn::make('kuota'),
                TextColumn::make('peserta_count')->counts('peserta')->label('Peserta'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('jenis')->options([
                    'open_house' => 'Open House', 'school_tour' => 'School Tour',
                    'webinar' => 'Webinar', 'lainnya' => 'Lainnya',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpmbKegiatan::route('/'),
            'create' => CreateSpmbKegiatan::route('/create'),
            'edit' => EditSpmbKegiatan::route('/{record}/edit'),
        ];
    }
}
