<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Akun;
use App\Models\EntitasAkuntansi;
use App\Models\Jurnal;
use App\Models\Orang;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use App\Services\Keuangan\JurnalService;
use App\Services\Keuangan\SinkronisasiEntitas;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class KeuanganTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->a = $this->periodeTerbuka([], 'FTN-01');
        $this->b = $this->periodeTerbuka([], 'FTN-02');
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    public function test_sinkronisasi_entitas_membuat_yayasan_cabang_sekolah(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();

        $this->assertDatabaseHas('entitas_akuntansi', ['tipe' => 'yayasan', 'kode' => 'YY']);
        $this->assertDatabaseHas('entitas_akuntansi', ['tipe' => 'cabang', 'cabang_id' => $this->a['cabang']]);
        $this->assertDatabaseHas('entitas_akuntansi', ['tipe' => 'cabang', 'cabang_id' => $this->b['cabang']]);
        $this->assertDatabaseHas('entitas_akuntansi', ['tipe' => 'sekolah', 'sekolah_id' => $this->a['sekolah']]);
        $this->assertDatabaseHas('entitas_akuntansi', ['tipe' => 'sekolah', 'sekolah_id' => $this->b['sekolah']]);
    }

    public function test_jurnal_service_buat_draft_dan_posting(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();
        $entitasId = $hasil['cabang'][$this->a['cabang']];

        $akunDebit = Akun::create([
            'entitas_id' => $entitasId, 'kode' => '1-100', 'nama' => 'Kas',
            'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);
        $akunKredit = Akun::create([
            'entitas_id' => $entitasId, 'kode' => '4-100', 'nama' => 'Pendapatan',
            'tipe' => 'pendapatan', 'saldo_normal' => 'kredit', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(JurnalService::class);

        $jurnal = $service->buatDraft([
            'entitas_id' => $entitasId,
            'cabang_id' => $this->a['cabang'],
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Penerimaan kas',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akunDebit->id, 'debit' => 1000000, 'kredit' => 0],
            ['akun_id' => $akunKredit->id, 'debit' => 0, 'kredit' => 1000000],
        ]);

        $this->assertSame('draft', $jurnal->status);
        $this->assertCount(2, $jurnal->baris);

        $jurnal = $service->posting($jurnal, $user->id);
        $this->assertSame('posted', $jurnal->status);
        $this->assertNotNull($jurnal->diposting_pada);
    }

    public function test_jurnal_tidak_seimbang_ditolak(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();
        $entitasId = $hasil['cabang'][$this->a['cabang']];

        $akun = Akun::create([
            'entitas_id' => $entitasId, 'kode' => '1-100', 'nama' => 'Kas',
            'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(JurnalService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak seimbang');
        $service->buatDraft([
            'entitas_id' => $entitasId,
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Tidak seimbang',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akun->id, 'debit' => 1000000, 'kredit' => 0],
            ['akun_id' => $akun->id, 'debit' => 0, 'kredit' => 500000],
        ]);
    }

    public function test_periode_ditutup_tidak_menerima_jurnal(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();
        $entitasId = $hasil['cabang'][$this->a['cabang']];

        PeriodeAkuntansi::create([
            'entitas_id' => $entitasId,
            'tahun' => (int) today()->year,
            'bulan' => (int) today()->month,
            'status' => 'ditutup',
        ]);

        $akun = Akun::create([
            'entitas_id' => $entitasId, 'kode' => '1-100', 'nama' => 'Kas',
            'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(JurnalService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ditutup');
        $service->buatDraft([
            'entitas_id' => $entitasId,
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Periode tertutup',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akun->id, 'debit' => 100, 'kredit' => 0],
            ['akun_id' => $akun->id, 'debit' => 0, 'kredit' => 100],
        ]);
    }

    public function test_admin_cabang_melihat_jurnal_cabangnya_saja(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();

        $akunA = Akun::create([
            'entitas_id' => $hasil['cabang'][$this->a['cabang']], 'kode' => '1-100',
            'nama' => 'Kas A', 'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);
        $akunB = Akun::create([
            'entitas_id' => $hasil['cabang'][$this->b['cabang']], 'kode' => '1-100',
            'nama' => 'Kas B', 'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(JurnalService::class);

        $service->buatDraft([
            'entitas_id' => $hasil['cabang'][$this->a['cabang']],
            'cabang_id' => $this->a['cabang'],
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Jurnal Cabang A',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akunA->id, 'debit' => 100, 'kredit' => 0],
            ['akun_id' => $akunA->id, 'debit' => 0, 'kredit' => 100],
        ]);

        $service->buatDraft([
            'entitas_id' => $hasil['cabang'][$this->b['cabang']],
            'cabang_id' => $this->b['cabang'],
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Jurnal Cabang B',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akunB->id, 'debit' => 100, 'kredit' => 0],
            ['akun_id' => $akunB->id, 'debit' => 0, 'kredit' => 100],
        ]);

        $this->actingAs($user)
            ->get(\App\Filament\Resources\Keuangan\JurnalResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Jurnal Cabang A')
            ->assertDontSee('Jurnal Cabang B');
    }

    public function test_jurnal_posting_dua_kali_ditolak(): void
    {
        $hasil = app(SinkronisasiEntitas::class)->jalankan();
        $entitasId = $hasil['cabang'][$this->a['cabang']];

        $akun = Akun::create([
            'entitas_id' => $entitasId, 'kode' => '1-100', 'nama' => 'Kas',
            'tipe' => 'aset', 'saldo_normal' => 'debit', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('kepala1', Peran::KepalaCabang, $this->a['cabang']);
        $service = app(JurnalService::class);

        $jurnal = $service->buatDraft([
            'entitas_id' => $entitasId,
            'cabang_id' => $this->a['cabang'],
            'tanggal' => today()->toDateString(),
            'keterangan' => 'Test posting ganda',
            'dibuat_oleh' => $user->id,
        ], [
            ['akun_id' => $akun->id, 'debit' => 100, 'kredit' => 0],
            ['akun_id' => $akun->id, 'debit' => 0, 'kredit' => 100],
        ]);

        $service->posting($jurnal, $user->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hanya jurnal draft');
        $service->posting($jurnal, $user->id);
    }
}
