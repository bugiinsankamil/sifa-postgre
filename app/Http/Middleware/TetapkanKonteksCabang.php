<?php

namespace App\Http\Middleware;

use App\Support\CabangKonteks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan lingkup cabang dari peran pengguna.
 * Asumsi: spatie/laravel-permission dengan teams = true dan team_foreign_key = 'cabang_id'
 * (nilai 0 = peran tingkat yayasan, berlaku untuk seluruh cabang).
 * Daftarkan di grup middleware 'web' setelah middleware sesi/autentikasi.
 */
class TetapkanKonteksCabang
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $cabangIds = DB::table('model_has_roles')
                ->where('model_type', $user->getMorphClass())
                ->where('model_id', $user->getKey())
                ->pluck('cabang_id')
                ->map(fn ($id) => (int) $id);

            $global = $cabangIds->contains(fn (int $id) => $id === 0);

            app(CabangKonteks::class)->tetapkan($global ? null : $cabangIds->all());
        }

        return $next($request);
    }
}
