<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends BaseModel
{
    use SoftDeletes;

    protected $guarded = [];

    public function orang(): BelongsTo
    {
        return $this->belongsTo(Orang::class, 'orang_id');
    }

    public function rombelSiswa(): HasMany
    {
        return $this->hasMany(RombelSiswa::class, 'siswa_id');
    }

    public function rombelSiswaAktif(): HasMany
    {
        return $this->hasMany(RombelSiswa::class, 'siswa_id')
            ->whereNull('tanggal_keluar')
            ->where('status', 'aktif');
    }

    public function rombelAktif(): HasOne
    {
        return $this->hasOne(Rombel::class, 'id', 'rombel_id')->through(RombelSiswa::class, 'siswa_id', 'rombel_id');
    }

    protected function casts(): array
    {
        return ['tanggal_masuk_yayasan' => 'date'];
    }
}
