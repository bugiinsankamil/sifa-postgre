<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateHariLibur;
use App\Filament\Resources\Sdm\Pages\EditHariLibur;
use App\Filament\Resources\Sdm\Pages\ListHariLibur;
use App\Models\Cabang;
use App\Models\HariLibur;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HariLiburResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = HariLibur::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 14;
    protected static ?string $navigationLabel = 'Hari Libur';
    protected static ?string $modelLabel = 'hari libur';
    protected static ?string $pluralModelLabel = 'hari libur';
    protected static ?string $slug = 'sdm/hari-libur';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data hari libur')->columns(2)->schema([
                Select::make('cabang_id')->label('Cabang')
                    ->options(fn () => collect(['' => 'Yayasan (semua cabang)'])
                        ->merge(Cabang::query()->orderBy('kode')->pluck('nama', 'id')->all())
                        ->all())
                    ->nullable()->searchable(),
                DatePicker::make('tanggal')->required(),
                TextInput::make('nama')->required()->maxLength(100),
                Toggle::make('is_cuti_bersama')->label('Cuti bersama')->default(false),
                Textarea::make('keterangan')->nullable()->rows(2)->columnSpan(2),
            ])->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cabang.nama')->label('Cabang')->placeholder('Yayasan')->sortable(),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nama')->searchable()->sortable(),
                IconColumn::make('is_cuti_bersama')->boolean()->label('Cuti bersama'),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHariLibur::route('/'),
            'create' => CreateHariLibur::route('/create'),
            'edit' => EditHariLibur::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_status', []));
    }

    public static function canEdit(Model $record): bool
    {
        return static::punyaPeranSdm(config('sdm.peran_status', []));
    }

    public static function canDelete(Model $record): bool
    {
        // Hari libur yang sudah digunakan dalam perhitungan cuti tidak boleh dihapus
        // agar histori perhitungan cuti tetap konsisten.
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        throw new \App\Exceptions\Sdm\SdmException('Hari libur tidak boleh dihapus karena merusak histori perhitungan cuti.');
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = app(CabangKonteks::class)->cabangDiizinkan();

        return parent::getEloquentQuery()
            ->when($ids !== null, fn (Builder $q) => $q->where(function (Builder $query) use ($ids) {
                $query->whereNull('cabang_id')->orWhereIn('cabang_id', $ids);
            }));
    }
}