<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateJenisCuti;
use App\Filament\Resources\Sdm\Pages\EditJenisCuti;
use App\Filament\Resources\Sdm\Pages\ListJenisCuti;
use App\Models\JenisCuti;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class JenisCutiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = JenisCuti::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 12;
    protected static ?string $navigationLabel = 'Jenis Cuti';
    protected static ?string $modelLabel = 'jenis cuti';
    protected static ?string $pluralModelLabel = 'jenis cuti';
    protected static ?string $slug = 'sdm/jenis-cuti';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data jenis cuti')->columns(2)->schema([
                TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('nama')->required()->maxLength(100),
                TextInput::make('kuota_hari_tahunan')->label('Kuota tahunan (hari)')
                    ->numeric()->minValue(0)->suffix('hari')->nullable(),
                Toggle::make('is_dibayar')->label('Dibayar')->default(true),
                Toggle::make('perlu_lampiran')->label('Perlu lampiran')->default(false),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('kuota_hari_tahunan')->label('Kuota/tahun')->suffix(' hari')->sortable(),
                IconColumn::make('is_dibayar')->boolean()->label('Dibayar'),
                IconColumn::make('perlu_lampiran')->boolean()->label('Lampiran'),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenisCuti::route('/'),
            'create' => CreateJenisCuti::route('/create'),
            'edit' => EditJenisCuti::route('/{record}/edit'),
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
        // Jenis cuti yang sudah memiliki histori pengajuan/saldo tidak boleh dihapus;
        // nonaktifkan (is_aktif = false) sebagai pengganti.
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function handleRecordDeletion(\App\Models\Model $record, array $params): void
    {
        throw new \App\Exceptions\Sdm\SdmException('Jenis cuti tidak boleh dihapus. Nonaktifkan jenis cuti tersebut jika tidak lagi digunakan.');
    }
}