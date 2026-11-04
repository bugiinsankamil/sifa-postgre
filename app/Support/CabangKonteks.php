<?php

namespace App\Support;

/**
 * Menyimpan cabang mana saja yang boleh diakses pada request yang sedang berjalan.
 *
 * Daftarkan sebagai scoped singleton di AppServiceProvider:
 *     $this->app->scoped(\App\Support\CabangKonteks::class);
 */
class CabangKonteks
{
    /** @var int[]|null null = tanpa batas */
    private ?array $cabangIds = null;

    /** @param int[]|null $cabangIds */
    public function tetapkan(?array $cabangIds): void
    {
        $this->cabangIds = $cabangIds === null ? null : array_values(array_unique(array_map('intval', $cabangIds)));
    }

    /** @return int[]|null */
    public function cabangDiizinkan(): ?array
    {
        return $this->cabangIds;
    }

    public function cabangTunggal(): ?int
    {
        return ($this->cabangIds !== null && count($this->cabangIds) === 1) ? $this->cabangIds[0] : null;
    }
}
