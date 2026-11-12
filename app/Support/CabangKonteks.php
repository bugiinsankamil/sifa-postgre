<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cabang mana saja yang boleh diakses pada request yang sedang berjalan.
 *
 * Diturunkan langsung dari pengguna yang sedang login (peran per cabang dari spatie, team 0 = seluruh cabang),
 * jadi tetap benar pada request Livewire/Filament tanpa bergantung pada urutan middleware. Tanpa pengguna
 * login (console, queue, formulir publik), lingkup tidak dibatasi.
 *
 * Daftarkan sebagai scoped singleton:  $this->app->scoped(\App\Support\CabangKonteks::class);
 */
class CabangKonteks
{
    /** @var int[]|null null = tanpa batas */
    private ?array $cabangIds = null;

    private bool $ditetapkan = false;

    private ?Collection $peran = null;

    private int|string|null $penggunaDimuat = null;

    /** Menetapkan lingkup secara eksplisit (mengalahkan penurunan otomatis dari pengguna). */
    public function tetapkan(?array $cabangIds): void
    {
        $this->cabangIds = $cabangIds === null ? null : array_values(array_unique(array_map('intval', $cabangIds)));
        $this->ditetapkan = true;
    }

    /** Hapus cache peran (dipanggil setelah peran pengguna berubah). */
    public function lupakan(): void
    {
        $this->peran = null;
        $this->penggunaDimuat = null;
        $this->cabangIds = null;
        $this->ditetapkan = false;
    }

    /** @return int[]|null null = tanpa batas; array kosong = tidak ada akses */
    public function cabangDiizinkan(): ?array
    {
        if ($this->ditetapkan) {
            return $this->cabangIds;
        }

        $peran = $this->peranPengguna();
        if ($peran === null) {
            return null;
        }

        $ids = $peran->pluck('cabang_id')->map(fn ($id) => (int) $id);

        return $ids->contains(fn (int $id) => $id === 0) ? null : $ids->unique()->values()->all();
    }

    public function cabangTunggal(): ?int
    {
        $ids = $this->cabangDiizinkan();

        return ($ids !== null && count($ids) === 1) ? $ids[0] : null;
    }

    /** Apakah pengguna yang login memegang salah satu peran (di cabang mana pun). */
    public function punyaPeran(array $nama): bool
    {
        $peran = $this->peranPengguna();

        return $peran !== null && $peran->pluck('nama')->intersect($nama)->isNotEmpty();
    }

    private function peranPengguna(): ?Collection
    {
        $pengguna = auth()->user();
        if ($pengguna === null) {
            return null;
        }

        if ($this->peran === null || $this->penggunaDimuat !== $pengguna->getKey()) {
            $this->peran = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', $pengguna->getMorphClass())
                ->where('model_has_roles.model_id', $pengguna->getKey())
                ->get(['roles.name as nama', 'model_has_roles.cabang_id']);
            $this->penggunaDimuat = $pengguna->getKey();
        }

        return $this->peran;
    }
}
