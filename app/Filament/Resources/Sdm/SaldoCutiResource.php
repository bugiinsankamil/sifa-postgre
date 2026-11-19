<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\ListSaldoCuti;
use App\Models\Pegawai;
use App\Models\SaldoCuti;
use App\Services\Sdm\MutasiSaldoCutiService;
use App\Support\CabangKonteks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SaldoCutiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = SaldoCuti::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Saldo Cuti';
    protected static ?string $modelLabel = 'saldo cuti';
    protected static ?string $pluralModelLabel = 'saldo cuti';
    protected static ?string $slug = 'sdm/saldo-cuti';
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Pegawai')->searchable()->sortable(),
                TextColumn::make('jenisCuti.nama')->label('Jenis cuti')->searchable()->sortable(),
                TextColumn::make('tahun')->sortable(),
                TextColumn::make('kuota')->suffix(' hari')->sortable(),
                TextColumn::make('terpakai')->suffix(' hari')->sortable(),
                TextColumn::make('dibawa')->suffix(' hari')->sortable(),
                TextColumn::make('sisa')->label('Sisa')->state(fn (SaldoCuti $record): string => $record->sisa().' hari'),
            ])
            ->defaultSort('tahun', 'desc')
            ->filters([])
            ->recordActions([
                Action::make('sesuaikan')->label('Sesuaikan saldo')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->authorize(fn () => static::punyaPeranSdm(config('sdm.peran_status', [])))
                    ->schema([
                        TextInput::make('saldo_baru')->label('Sisa saldo baru (hari)')
                            ->required()->numeric()->minValue(0)->step(0.1),
                        Textarea::make('alasan')->label('Alasan penyesuaian')->required()->rows(2),
                    ])
                    ->action(function (SaldoCuti $record, array $data) {
                        try {
                            app(MutasiSaldoCutiService::class)->sesuaikan(
                                $record,
                                $data['saldo_baru'],
                                $data['alasan'],
                                auth()->user()
                            );
                            Notification::make()->title('Saldo disesuaikan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaldoCuti::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = app(CabangKonteks::class)->cabangDiizinkan();

        return parent::getEloquentQuery()
            ->with(['pegawai.orang', 'jenisCuti'])
            ->when($ids !== null, fn (Builder $q) => $q->whereHas('pegawai.penugasan', fn ($p) => $p->whereIn('cabang_id', $ids)));
    }
}