<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateMataPelajaran;
use App\Filament\Resources\Akademik\Pages\EditMataPelajaran;
use App\Filament\Resources\Akademik\Pages\ListMataPelajaran;
use App\Models\Jenjang;
use App\Models\MataPelajaran;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MataPelajaranResource extends Resource
{
    protected static ?string $model = MataPelajaran::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Mata Pelajaran';
    protected static ?string $modelLabel = 'mata pelajaran';
    protected static ?string $pluralModelLabel = 'mata pelajaran';
    protected static ?string $slug = 'akademik/mata-pelajaran';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data mata pelajaran')->columns(2)->schema([
                TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('nama')->required()->maxLength(100),
                Select::make('jenjang_id')->label('Jenjang')
                    ->options(fn () => Jenjang::query()->orderBy('kode')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                Select::make('kelompok_ref')->label('Kelompok')
                    ->options([
                        'umum' => 'Umum',
                        'agama' => 'Agama',
                        'muatan_lokal' => 'Muatan Lokal',
                        'pilihan' => 'Pilihan',
                    ])
                    ->required()
                    ->default('umum'),
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
                TextColumn::make('jenjang.nama')->label('Jenjang'),
                TextColumn::make('kelompok_ref')->label('Kelompok'),
                ToggleColumn::make('is_aktif')->label('Aktif'),
            ])
            ->defaultSort('kode', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMataPelajaran::route('/'),
            'create' => CreateMataPelajaran::route('/create'),
            'edit' => EditMataPelajaran::route('/{record}/edit'),
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
        return !$record->struktur()->exists() && !$record->kompetensi()->exists() && !$record->tujuan()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
