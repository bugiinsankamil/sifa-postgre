<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateJabatan;
use App\Filament\Resources\Sdm\Pages\EditJabatan;
use App\Filament\Resources\Sdm\Pages\ListJabatan;
use App\Models\Jabatan;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class JabatanResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = Jabatan::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Jabatan';
    protected static ?string $modelLabel = 'jabatan';
    protected static ?string $pluralModelLabel = 'jabatan';
    protected static ?string $slug = 'sdm/jabatan';
    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data jabatan')->columns(2)->schema([
                TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('nama')->required(),
                Select::make('kategori')->options([
                    'struktural' => 'Struktural', 'fungsional' => 'Fungsional', 'pendukung' => 'Pendukung',
                ])->required(),
                Select::make('unit_kerja_id')->label('Unit kerja')
                    ->options(fn () => DB::table('unit_kerja')->pluck('nama', 'id'))->nullable()->searchable(),
                Toggle::make('is_aktif')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('kategori')->badge(),
                TextColumn::make('unitKerja.nama')->label('Unit kerja')->placeholder('-'),
                IconColumn::make('is_aktif')->boolean()->label('Aktif'),
            ])
            ->defaultSort('kode')
            ->filters([
                SelectFilter::make('kategori')->options([
                    'struktural' => 'Struktural', 'fungsional' => 'Fungsional', 'pendukung' => 'Pendukung',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJabatan::route('/'),
            'create' => CreateJabatan::route('/create'),
            'edit' => EditJabatan::route('/{record}/edit'),
        ];
    }
}
