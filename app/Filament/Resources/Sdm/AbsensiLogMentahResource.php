<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\ListAbsensiLogMentah;
use App\Models\AbsensiLogMentah;
use App\Services\Sdm\AbsensiLogProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pemantauan log mentah absensi: belum diproses, gagal, dan sudah diproses. Hanya-baca; operator boleh mengulang log gagal.
 */
class AbsensiLogMentahResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = AbsensiLogMentah::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Log Absensi Gagal';
    protected static ?string $modelLabel = 'log absensi';
    protected static ?string $pluralModelLabel = 'log absensi mentah';
    protected static ?string $slug = 'sdm/log-absensi';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['pegawai.orang', 'perangkat']))
            ->columns([
                TextColumn::make('waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Pegawai')->placeholder('Belum terpetakan')->searchable(),
                TextColumn::make('pin')->label('PIN')->placeholder('-'),
                TextColumn::make('perangkat.nama')->label('Perangkat')->placeholder('-'),
                TextColumn::make('tipe')->badge(),
                TextColumn::make('status_proses')->label('Status')->badge()
                    ->state(fn (AbsensiLogMentah $r) => match (true) {
                        $r->diproses_pada !== null => 'Diproses',
                        $r->percobaan >= AbsensiLogProcessor::MAKS_PERCOBAAN => 'Gagal (menyerah)',
                        $r->galat !== null => 'Gagal',
                        $r->pegawai_id === null => 'Belum terpetakan',
                        default => 'Antre',
                    })
                    ->color(fn (string $s) => match ($s) {
                        'Diproses' => 'success', 'Antre' => 'gray', 'Belum terpetakan' => 'warning', default => 'danger',
                    }),
                TextColumn::make('percobaan')->label('Percobaan'),
                TextColumn::make('galat')->label('Galat')->limit(60)->tooltip(fn (AbsensiLogMentah $r) => $r->galat),
            ])
            ->defaultSort('waktu', 'desc')
            ->filters([
                Filter::make('gagal')->label('Gagal diproses')->default()
                    ->query(fn (Builder $q) => $q->whereNull('diproses_pada')->whereNotNull('galat')),
                Filter::make('belum_terpetakan')->label('Belum terpetakan')
                    ->query(fn (Builder $q) => $q->whereNull('pegawai_id')),
                Filter::make('antre')->label('Antre diproses')
                    ->query(fn (Builder $q) => $q->whereNull('diproses_pada')->whereNull('galat')),
            ])
            ->recordActions([
                Action::make('ulangi')
                    ->label('Ulangi')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (AbsensiLogMentah $r) => $r->diproses_pada === null && $r->galat !== null)
                    ->authorize(fn () => static::canUbahStatus())
                    ->requiresConfirmation()
                    ->action(function (AbsensiLogMentah $record) {
                        try {
                            app(AbsensiLogProcessor::class)->ulangiLogGagal($record);
                            Notification::make()->title('Log dijadwalkan ulang')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbsensiLogMentah::route('/'),
        ];
    }
}
