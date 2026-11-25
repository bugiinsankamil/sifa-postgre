<?php

namespace App\Filament\Resources\Akademik;

use App\Filament\Resources\Akademik\Pages\CreateSkalaPenilaian;
use App\Filament\Resources\Akademik\Pages\EditSkalaPenilaian;
use App\Filament\Resources\Akademik\Pages\ListSkalaPenilaian;
use App\Models\Kurikulum;
use App\Models\Sekolah;
use App\Models\SkalaPenilaian;
use App\Support\CabangKonteks;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SkalaPenilaianResource extends Resource
{
    protected static ?string $model = SkalaPenilaian::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Akademik';
    protected static ?int $navigationSort = 11;
    protected static ?string $navigationLabel = 'Skala Penilaian';
    protected static ?string $slug = 'akademik/skala-penilaian';
    protected static ?string $recordTitleAttribute = 'kode';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sekolah_id')->label('Sekolah')->relationship('sekolah', 'nama')->searchable()->nullable(),
            Select::make('kurikulum_id')->label('Kurikulum')->relationship('kurikulum', 'kode')->searchable()->nullable(),
            TextInput::make('kode')->label('Kode')->required()->unique(ignoreRecord: true)->maxLength(50),
            TextInput::make('nama')->label('Nama')->required()->maxLength(100),
            Select::make('jenis')->label('Jenis')->enum([
                'angka' => 'Angka',
                'predikat' => 'Predikat',
                'capaian' => 'Capaian',
                'deskriptif' => 'Deskriptif',
            ])->default('angka'),
            Toggle::make('is_aktif')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sekolah.nama')->label('Sekolah')->sortable()->searchable(),
                TextColumn::make('kurikulum.kode')->label('Kurikulan')->sortable()->searchable(),
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->sortable(),
                TextColumn::make('is_aktif')->label('Aktif')->toggle()->sortable(),
            ])
            ->defaultSort('kode', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSkalaPenilaian::route('/'),
            'create' => CreateSkalaPenilaian::route('/create'),
            'edit' => EditSkalaPenilaian::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return app(CabangKonteks::class)->punyaPeran([
            'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah', 'pj_program',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sekolah', 'kurikulum']);
    }
}