<?php

namespace App\Filament\Resources\Sdm;

use App\Filament\Concerns\AksesSdm;
use App\Filament\Resources\Sdm\Pages\CreatePengajuanIzin;
use App\Filament\Resources\Sdm\Pages\EditPengajuanIzin;
use App\Filament\Resources\Sdm\Pages\ListPengajuanIzin;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Filament\Resources\Sdm\PegawaiResource;
use App\Services\Sdm\IzinService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PengajuanIzinResource extends Resource
{
    use AksesSdm;

    protected static ?string $model = PengajuanIzin::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';
    protected static ?int $navigationSort = 12;
    protected static ?string $navigationLabel = 'Pengajuan Izin';
    protected static ?string $modelLabel = 'pengajuan izin';
    protected static ?string $pluralModelLabel = 'pengajuan izin';
    protected static ?string $slug = 'sdm/izin';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pengajuan izin')->columns(2)->schema([
                Select::make('pegawai_id')->label('Pegawai')
                    ->options(fn () => PegawaiResource::getEloquentQuery()->get()->pluck('orang.nama_lengkap', 'id'))
                    ->required()->searchable(),
                Select::make('jenis')->label('Jenis Izin')
                    ->options([
                        'izin' => 'Izin',
                        'sakit' => 'Sakit',
                        'dinas_luar' => 'Dinas Luar',
                    ])->required(),
                DatePicker::make('tanggal_mulai')->required(),
                DatePicker::make('tanggal_selesai')->required()->afterOrEqual('tanggal_mulai'),
                Textarea::make('alasan')->columnSpan(2)->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Pegawai')->searchable()->sortable(),
                TextColumn::make('jenis')->badge()->color(fn (string $s) => match ($s) {
                    'izin' => 'info', 'sakit' => 'warning', 'dinas_luar' => 'primary',
                }),
                TextColumn::make('tanggal_mulai')->date('d/m/Y'),
                TextColumn::make('tanggal_selesai')->date('d/m/Y'),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'disetujui' => 'success', 'ditolak' => 'danger',
                    'dibatalkan' => 'gray', default => 'warning',
                }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui',
                    'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan',
                ]),
                SelectFilter::make('jenis')->options([
                    'izin' => 'Izin', 'sakit' => 'Sakit', 'dinas_luar' => 'Dinas Luar',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PengajuanIzin $r) => $r->status === 'diajukan')
                    ->authorize(fn () => static::canApprovePengajuan())
                    ->action(function (PengajuanIzin $record) {
                        try {
                            app(IzinService::class)->setujui($record, auth()->user());
                            Notification::make()->title('Izin disetujui')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PengajuanIzin $r) => $r->status === 'diajukan')
                    ->authorize(fn () => static::canApprovePengajuan())
                    ->schema([
                        Textarea::make('alasan')->label('Alasan penolakan')->rows(2),
                    ])
                    ->action(function (PengajuanIzin $record, array $data) {
                        try {
                            app(IzinService::class)->tolak($record, auth()->user(), $data['alasan'] ?? null);
                            Notification::make()->title('Izin ditolak')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengajuanIzin::route('/'),
            'create' => CreatePengajuanIzin::route('/create'),
            'edit' => EditPengajuanIzin::route('/{record}/edit'),
        ];
    }
}
