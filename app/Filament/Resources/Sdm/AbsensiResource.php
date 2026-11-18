<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreateAbsensi;
use App\Filament\Resources\Sdm\Pages\EditAbsensi;
use App\Filament\Resources\Sdm\Pages\ListAbsensi;
use App\Models\AbsensiPegawai;
use App\Models\Cabang;
use App\Models\Pegawai;
use App\Models\ShiftKerja;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Services\Sdm\AbsensiService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AbsensiResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = AbsensiPegawai::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Absensi Harian';
    protected static ?string $modelLabel = 'absensi';
    protected static ?string $pluralModelLabel = 'absensi harian';
    protected static ?string $slug = 'sdm/absensi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data absensi')->columns(2)->schema([
                Select::make('pegawai_id')->label('Pegawai')
                    ->options(fn () => PegawaiResource::getEloquentQuery()->get()->pluck('orang.nama_lengkap', 'id'))
                    ->required()->searchable()->disabledOn('edit'),
                DatePicker::make('tanggal')->required()->disabledOn('edit'),
                Select::make('status')->options([
                    'hadir' => 'Hadir', 'terlambat' => 'Terlambat',
                    'izin' => 'Izin', 'sakit' => 'Sakit',
                    'cuti' => 'Cuti', 'dinas' => 'Dinas', 'alpa' => 'Alpa',
                ])->required()->default('hadir'),
                Select::make('shift_id')->label('Shift')
                    ->options(fn () => ShiftKerja::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->nullable()->searchable(),
                TimePicker::make('jam_masuk')->seconds(false),
                TimePicker::make('jam_pulang')->seconds(false),
                TextInput::make('keterangan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Pegawai')->searchable()->sortable(),
                TextColumn::make('tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('jam_masuk')->label('Masuk'),
                TextColumn::make('jam_pulang')->label('Pulang'),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'hadir' => 'success', 'terlambat' => 'warning',
                    'izin', 'sakit', 'cuti', 'dinas' => 'info',
                    'alpa' => 'danger', default => 'gray',
                }),
                TextColumn::make('terlambat_menit')->suffix(' mnt')->label('Telat'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'hadir' => 'Hadir', 'terlambat' => 'Terlambat',
                    'izin' => 'Izin', 'sakit' => 'Sakit',
                    'cuti' => 'Cuti', 'dinas' => 'Dinas', 'alpa' => 'Alpa',
                ]),
                SelectFilter::make('cabang_id')->label('Cabang')
                    ->options(fn () => Cabang::query()->orderBy('kode')->pluck('nama', 'id')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('catat_manual')
                    ->label('Koreksi')
                    ->icon('heroicon-o-pencil-square')
                    ->authorize(fn (AbsensiPegawai $record) => static::canEdit($record))
                    ->schema([
                        Select::make('status')->options([
                            'hadir' => 'Hadir', 'terlambat' => 'Terlambat',
                            'izin' => 'Izin', 'sakit' => 'Sakit',
                            'cuti' => 'Cuti', 'dinas' => 'Dinas', 'alpa' => 'Alpa',
                        ])->required(),
                        TimePicker::make('jam_masuk')->seconds(false),
                        TimePicker::make('jam_pulang')->seconds(false),
                        Textarea::make('keterangan')->rows(2),
                    ])
                    ->action(function (AbsensiPegawai $record, array $data) {
                        try {
                            app(AbsensiService::class)->catatManual($record->pegawai, $record->tanggal->toDateString(), $data, auth()->id());
                            Notification::make()->title('Absensi diperbarui')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbsensi::route('/'),
            'create' => CreateAbsensi::route('/create'),
            'edit' => EditAbsensi::route('/{record}/edit'),
        ];
    }
}
