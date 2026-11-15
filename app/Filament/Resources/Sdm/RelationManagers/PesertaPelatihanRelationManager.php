<?php

namespace App\Filament\Resources\Sdm\RelationManagers;

use App\Models\PelatihanPeserta;
use App\Models\Pegawai;
use App\Services\Sdm\PelatihanService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PesertaPelatihanRelationManager extends RelationManager
{
    protected static string $relationship = 'peserta';
    protected static ?string $title = 'Peserta';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('pegawai_id')->label('Pegawai')
                ->options(fn () => Pegawai::query()->with('orang')->get()
                    ->mapWithKeys(fn ($p) => [$p->id => $p->orang->nama_lengkap.' ('.$p->nip_yayasan.')']))
                ->required()->searchable(),
            Select::make('status')->options([
                'terdaftar' => 'Terdaftar', 'hadir' => 'Hadir',
                'lulus' => 'Lulus', 'tidak_hadir' => 'Tidak hadir',
            ])->default('terdaftar')->required(),
            TextInput::make('nilai')->numeric()->minValue(0)->maxValue(100),
            TextInput::make('nomor_sertifikat'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('pegawai.orang.nama_lengkap')->label('Nama'),
                TextColumn::make('pegawai.nip_yayasan')->label('NIP'),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'lulus' => 'success', 'hadir' => 'info',
                    'tidak_hadir' => 'danger', default => 'gray',
                }),
                TextColumn::make('nilai'),
                TextColumn::make('nomor_sertifikat')->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->action(function (array $data) {
                    try {
                        $peserta = app(PelatihanService::class)->daftarkan(
                            $this->getOwnerRecord(), Pegawai::findOrFail($data['pegawai_id']),
                        );
                        $peserta->update([
                            'nilai' => $data['nilai'] ?? null,
                            'nomor_sertifikat' => $data['nomor_sertifikat'] ?? null,
                        ]);
                        if (($data['status'] ?? 'terdaftar') !== 'terdaftar') {
                            app(PelatihanService::class)->catatHasil(
                                $peserta, $data['status'], $data['nilai'] ?? null, $data['nomor_sertifikat'] ?? null,
                            );
                        }
                        Notification::make()->title('Peserta ditambahkan')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
            ])
            ->recordActions([
                Action::make('catat_hasil')
                    ->label('Catat hasil')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Select::make('status')->options([
                            'hadir' => 'Hadir', 'lulus' => 'Lulus', 'tidak_hadir' => 'Tidak hadir',
                        ])->required(),
                        TextInput::make('nilai')->numeric()->minValue(0)->maxValue(100),
                        TextInput::make('nomor_sertifikat'),
                    ])
                    ->fillForm(fn (PelatihanPeserta $r) => [
                        'status' => $r->status === 'terdaftar' ? 'hadir' : $r->status,
                        'nilai' => $r->nilai, 'nomor_sertifikat' => $r->nomor_sertifikat,
                    ])
                    ->action(function (PelatihanPeserta $record, array $data) {
                        try {
                            app(PelatihanService::class)->catatHasil(
                                $record, $data['status'], $data['nilai'] ?? null, $data['nomor_sertifikat'] ?? null,
                            );
                            Notification::make()->title('Hasil dicatat')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
