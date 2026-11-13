<?php

namespace App\Filament\Resources\Pesan;

use App\Filament\Resources\Pesan\Pages\EditTemplatPesan;
use App\Filament\Resources\Pesan\Pages\ListTemplatPesan;
use App\Models\TemplatPesan;
use App\Support\CabangKonteks;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TemplatPesanResource extends Resource
{
    protected static ?string $model = TemplatPesan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Pesan';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Templat pesan';

    protected static ?string $modelLabel = 'templat pesan';

    protected static ?string $pluralModelLabel = 'templat pesan';

    protected static ?string $slug = 'pesan/templat';

    public static function canViewAny(): bool
    {
        return app(CabangKonteks::class)->punyaPeran(config('pesan.peran_templat', []));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')->label('Judul (dipakai kanal email)')->maxLength(255),
            Textarea::make('isi')
                ->label('Isi pesan')
                ->required()
                ->rows(10)
                ->helperText(fn (?Model $record) => 'Variabel yang tersedia: '.collect($record?->variabel ?? [])->map(fn (string $v) => '{{'.$v.'}}')->implode(', '))
                ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $dikenal = $record?->variabel ?? [];
                    preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', (string) $value, $cocok);
                    $asing = array_diff(array_unique($cocok[1]), $dikenal);

                    if ($asing !== []) {
                        $fail('Variabel tidak dikenal: '.implode(', ', $asing).'. Yang tersedia: '.implode(', ', $dikenal).'.');
                    }
                }),
            Toggle::make('is_aktif')->label('Aktif')->helperText('Templat nonaktif tidak menghasilkan pesan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('kanal')->badge(),
                TextColumn::make('isi')->limit(70),
                IconColumn::make('is_aktif')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplatPesan::route('/'),
            'edit' => EditTemplatPesan::route('/{record}/edit'),
        ];
    }
}
