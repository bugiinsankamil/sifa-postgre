<?php

namespace App\Models;

use App\Enums\Peran;
use App\Services\PenugasanPeran;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Menggantikan App\Models\User bawaan Laravel. Tambahkan trait HasApiTokens (Laravel\Sanctum)
 * bila Sanctum dipasang.
 */
class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $fillable = ['orang_id', 'username', 'email', 'password', 'is_aktif'];

    protected $hidden = ['password', 'remember_token'];

    private ?bool $sysadmin = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_aktif' => 'boolean',
        ];
    }

    public function orang(): BelongsTo
    {
        return $this->belongsTo(Orang::class);
    }

    /** Sysadmin (team 0), tidak bergantung pada cabang yang sedang aktif. Hasilnya disimpan per instans. */
    public function isSysadmin(): bool
    {
        return $this->sysadmin ??= app(PenugasanPeran::class)->punya($this, Peran::Sysadmin);
    }

    public function segarkanPeran(): void
    {
        $this->sysadmin = null;
        $this->unsetRelation('roles');
    }
}
