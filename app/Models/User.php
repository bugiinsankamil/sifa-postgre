<?php

namespace App\Models;

use App\Enums\Peran;
use App\Services\PenugasanPeran;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * Menggantikan App\Models\User bawaan Laravel. Tambahkan trait HasApiTokens (Laravel\Sanctum)
 * bila Sanctum dipasang.
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    use HasRoles, Notifiable;

    protected $fillable = ['orang_id', 'username', 'email', 'password', 'is_aktif'];

    protected $attributes = ['is_aktif' => true];

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

    /** Panel admin hanya untuk akun aktif yang memegang peran staf (config/akses.php). */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_aktif && $this->punyaPeranMana(config('akses.peran_panel', []));
    }

    /** Tabel users tidak punya kolom name; nama diambil dari data orang. */
    public function getFilamentName(): string
    {
        return $this->orang?->nama_lengkap ?? $this->username;
    }

    /** Apakah pengguna memegang salah satu peran, di cabang mana pun. */
    public function punyaPeranMana(array $nama): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $this->getMorphClass())
            ->where('model_has_roles.model_id', $this->getKey())
            ->whereIn('roles.name', $nama)
            ->exists();
    }
}
