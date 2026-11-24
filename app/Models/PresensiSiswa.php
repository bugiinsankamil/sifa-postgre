<?php

namespace App\Models;

use App\Models\Concerns\BerLingkupCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presensi siswa per rombel/tanggal.
 * jurnal_mengajar_id NULL = harian TK/SD; diisi = per jam SMP.
 * Unik (siswa_id, tanggal) untuk harian, dan (siswa_id, tanggal, jurnal_mengajar_id) untuk per jam,
 * dipastikan via partial unique index di migration (PostgreSQL: NULL dianggap beda).
 */
class PresensiSiswa extends BaseModel
{
    use BerLingkupCabang;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jurnalMengajar(): BelongsTo
    {
        return $this->belongsTo(JurnalMengajar::class, 'jurnal_mengajar_id');
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
