<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateKurikulum;
use App\Filament\Resources\Akademik\Pages\EditKurikulum;
use App\Filament\Resources\Akademik\Pages\ListKurikulum;
use App\Models\Kurikulum;
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

class KurikulumResource extends Resource
{
    protected static ?string $model = Kurikulum::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Kurikulum';
    protected static ?string $modelLabel = 'kurikulum';
    protected static ?string $pluralModelLabel = 'kurikulum';
    protected static ?string $slug = 'akademik/kurikulum';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data kurikulum')->columns(2)->schema([
                TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('nama')->required()->maxLength(100),
                TextInput::make('deskripsi')->maxLength(500),
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
                TextColumn::make('deskripsi')->limit(50),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKurikulum::route('/'),
            'create' => CreateKurikulum::route('/create'),
            'edit' => EditKurikulum::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(\App\Support\CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'pj_program',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        // Kurikulum yang sudah diterapkan ke sekolah tidak boleh dihapus; nonaktifkan sebagai pengganti.
        return !$record->sekolah()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function handleRecordDeletion(Model $record, array $params): void
    {
        if ($record->sekolah()->exists()) {
            throw new \App\Exceptions\Akademik\KurikulumException(
                'Kurikulum '.$record->kode.' sudah diterapkan ke sekolah. Nonaktifkan sebagai pengganti.'
            );
        }
    }
}
