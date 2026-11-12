<?php

namespace App\Filament\Resources\Spmb\Pages;

use App\Exceptions\Spmb\KonversiTidakBisa;
use App\Exceptions\Spmb\TransisiTidakValid;
use App\Filament\Resources\Spmb\SpmbPendaftarResource;
use App\Models\Rombel;
use App\Services\Spmb\AlurSeleksiSpmb;
use App\Services\Spmb\KonversiPendaftar;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSpmbPendaftar extends ViewRecord
{
    protected static string $resource = SpmbPendaftarResource::class;

    protected function getHeaderActions(): array
    {
        $status = fn (): string => (string) $this->record->status;

        return [
            Action::make('verifikasi')
                ->label('Tandai terverifikasi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $status() === 'terdaftar')
                ->requiresConfirmation()
                ->modalDescription('Semua berkas wajib harus berstatus valid.')
                ->action(fn () => $this->jalankan(
                    fn () => app(AlurSeleksiSpmb::class)->verifikasi($this->record),
                    'Berkas terverifikasi'
                )),

            Action::make('hasil')
                ->label('Tetapkan hasil')
                ->icon('heroicon-o-flag')
                ->color('primary')
                ->visible(fn () => in_array($status(), ['diverifikasi', 'seleksi', 'lulus', 'cadangan', 'tidak_lulus'], true))
                ->schema([
                    Select::make('keputusan')->options(['lulus' => 'Lulus', 'cadangan' => 'Cadangan', 'tidak_lulus' => 'Tidak lulus'])->required(),
                    Textarea::make('catatan'),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(AlurSeleksiSpmb::class)->tetapkanHasil($this->record, $data['keputusan'], (int) auth()->id(), $data['catatan'] ?? null),
                    'Hasil ditetapkan'
                )),

            Action::make('mulaiDaftarUlang')
                ->label('Mulai daftar ulang')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn () => $status() === 'lulus')
                ->requiresConfirmation()
                ->action(fn () => $this->jalankan(
                    fn () => app(AlurSeleksiSpmb::class)->mulaiDaftarUlang($this->record),
                    'Daftar ulang dimulai'
                )),

            Action::make('selesaiDaftarUlang')
                ->label('Daftar ulang selesai')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(fn () => $status() === 'daftar_ulang' && $this->record->daftarUlang?->status !== 'selesai')
                ->schema([
                    DatePicker::make('tanggal')->default(today())->required(),
                    Select::make('rombel_id')->label('Rombel (boleh dikosongkan)')
                        ->options(fn () => Rombel::query()
                            ->where('sekolah_id', $this->record->sekolah_id)
                            ->where('tahun_ajaran_id', $this->record->periode->tahun_ajaran_id)
                            ->orderBy('nama')->pluck('nama', 'id')),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(AlurSeleksiSpmb::class)->selesaikanDaftarUlang(
                        $this->record,
                        isset($data['rombel_id']) ? (int) $data['rombel_id'] : null,
                        $data['tanggal'] ?? null,
                    ),
                    'Daftar ulang selesai'
                )),

            Action::make('konversi')
                ->label('Jadikan siswa')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->visible(fn () => $status() === 'daftar_ulang'
                    && $this->record->daftarUlang?->status === 'selesai'
                    && $this->record->siswa_id === null)
                ->requiresConfirmation()
                ->modalHeading('Jadikan siswa?')
                ->modalDescription('Dibuat data siswa, orang tua/wali, keanggotaan sekolah, dan penempatan rombel (bila ada). Tindakan ini tidak dapat dibatalkan dari layar ini.')
                ->action(function () {
                    $this->jalankan(function () {
                        $siswa = app(KonversiPendaftar::class)->konversi($this->record);
                        $this->record->setRelation('siswa', $siswa);
                    }, 'Pendaftar sudah menjadi siswa');
                }),

            Action::make('mundur')
                ->label('Mengundurkan diri')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => ! in_array($status(), ['menjadi_siswa', 'mundur'], true))
                ->requiresConfirmation()
                ->schema([Textarea::make('alasan')])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(AlurSeleksiSpmb::class)->mundur($this->record, $data['alasan'] ?? null),
                    'Pendaftar ditandai mundur'
                )),

            EditAction::make()->label('Koreksi biodata'),
        ];
    }

    private function jalankan(Closure $aksi, string $judul): void
    {
        try {
            $aksi();
            $this->record->refresh();

            Notification::make()->title($judul)->success()->send();
        } catch (TransisiTidakValid|KonversiTidakBisa $e) {
            Notification::make()->title('Tidak dapat diproses')->body($e->getMessage())->danger()->send();
        }
    }
}
