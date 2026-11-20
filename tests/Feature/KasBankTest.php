<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Akun;
use App\Models\EntitasAkuntansi;
use App\Models\RekeningKas;
use App\Models\TransaksiKas;
use App\Models\User;
use App\Services\Keuangan\KasService;
use App\Services\Keuangan\RekonsiliasiBankService;
use App\Services\Keuangan\SinkronisasiEntitas;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class KasBankTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private array $data;
    private int $entitasCabangId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->data = $this->periodeTerbuka([], 'FTN-01');
        $hasilSinkronisasi = app(SinkronisasiEntitas::class)->jalankan();
        $this->entitasCabangId = $hasilSinkronisasi['cabang'][$this->data['cabang']];
    }

    private function pengguna(string $username, Peran $peran, ?int $cabangId = null): User
    {
        $orang = \App\Models\Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    private function entitasCabang(): int
    {
        return $this->entitasCabangId;
    }

    private function akun(int $entitas, string $kode, string $tipe, string $normal): Akun
    {
        return Akun::create([
            'entitas_id' => $entitas, 'kode' => $kode, 'nama' => $kode,
            'tipe' => $tipe, 'saldo_normal' => $normal, 'is_aktif' => true,
        ]);
    }

    private function rekening(int $entitas, int $cabang, string $nama, string $tipe, int $akunId): RekeningKas
    {
        return RekeningKas::create([
            'entitas_id' => $entitas, 'cabang_id' => $cabang, 'nama' => $nama,
            'tipe' => $tipe, 'akun_id' => $akunId, 'saldo_awal' => 0, 'is_aktif' => true,
        ]);
    }

    public function test_pemasukan_kas_membuat_transaksi_dan_jurnal_seimbang(): void
    {
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $pendapatan = $this->akun($entitas, '4-101', 'pendapatan', 'kredit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Kas Sekolah', 'kas', $kas->id);

        $user = $this->pengguna('kasir1', Peran::Admin, $this->data['cabang']);
        $result = app(KasService::class)->masuk([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 5000000,
            'akun_lawan_id' => $pendapatan->id,
            'keterangan' => 'SPP siswa',
        ], $user);

        $this->assertSame('posted', $result['transaksi']->status);
        $this->assertSame('masuk', $result['transaksi']->tipe);
        $this->assertNotNull($result['jurnal']);
        $this->assertSame('posted', $result['jurnal']->status);
        $this->assertTrue(bccomp((string) $result['jurnal']->totalDebit(), (string) $result['jurnal']->totalKredit(), 2) === 0);
        $this->assertDatabaseHas('transaksi_kas', ['id' => $result['transaksi']->id, 'status' => 'posted']);
    }

    public function test_pengeluaran_kas_dengan_akun_lawan(): void
    {
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $beban = $this->akun($entitas, '5-201', 'beban', 'debit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Kas Sekolah', 'kas', $kas->id);

        $user = $this->pengguna('kasir2', Peran::Admin, $this->data['cabang']);
        $result = app(KasService::class)->keluar([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 1200000,
            'akun_lawan_id' => $beban->id,
            'keterangan' => 'Listrik',
        ], $user);

        $this->assertSame('keluar', $result['transaksi']->tipe);
        $this->assertSame('posted', $result['jurnal']->status);
        $this->assertTrue(bccomp((string) $result['jurnal']->totalDebit(), (string) $result['jurnal']->totalKredit(), 2) === 0);
    }

    public function test_transfer_antara_rekening_membuat_dua_transaksi(): void
    {
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $bank = $this->akun($entitas, '1-102', 'aset', 'debit');
        $rKas = $this->rekening($entitas, $this->data['cabang'], 'Kas', 'kas', $kas->id);
        $rBank = $this->rekening($entitas, $this->data['cabang'], 'Bank', 'bank', $bank->id);

        $user = $this->pengguna('kasir3', Peran::Admin, $this->data['cabang']);
        $result = app(KasService::class)->transfer([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rKas->id,
            'rekening_tujuan_id' => $rBank->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 2000000,
            'keterangan' => 'Setoran bank',
        ], $user);

        $this->assertSame('transfer', $result['keluar']['transaksi']->tipe);
        $this->assertSame($rBank->id, $result['keluar']['transaksi']->rekening_tujuan_id);
        $this->assertSame('transfer', $result['masuk']['transaksi']->tipe);
        $this->assertSame($rKas->id, $result['masuk']['transaksi']->rekening_tujuan_id);
        $this->assertNotNull($result['keluar']['jurnal']);
        $this->assertNotNull($result['masuk']['jurnal']);
    }

    public function test_pembatalan_transaksi_void_jurnal_dan_tandai_batal(): void
    {
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $pendapatan = $this->akun($entitas, '4-101', 'pendapatan', 'kredit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Kas Sekolah', 'kas', $kas->id);

        $user = $this->pengguna('kasir4', Peran::KepalaCabang, $this->data['cabang']);
        $transaksi = app(KasService::class)->masuk([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 1000000,
            'akun_lawan_id' => $pendapatan->id,
        ], $user)['transaksi'];

        $dibatalkan = app(KasService::class)->batalkan($transaksi, $user->id, 'Ditolak oleh atasan');

        $this->assertSame('batal', $dibatalkan->status);
        $this->assertSame('void', $dibatalkan->jurnal->status);
    }

    public function test_tidak_bisa_masuk_kas_rekening_tidak_aktif(): void
    {
        $this->expectException(\RuntimeException::class);
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Kas NonAktif', 'kas', $kas->id);
        $rekening->update(['is_aktif' => false]);

        app(KasService::class)->masuk([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 100000,
            'akun_lawan_id' => $kas->id,
        ], $this->pengguna('kasir5', Peran::Admin, $this->data['cabang']));
    }

    public function test_tidak_bisa_transfer_ke_rekening_sama(): void
    {
        $entitas = $this->entitasCabang();
        $kas = $this->akun($entitas, '1-101', 'aset', 'debit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Kas', 'kas', $kas->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        app(KasService::class)->transfer([
            'entitas_id' => $entitas,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id,
            'rekening_tujuan_id' => $rekening->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 100000,
        ], $this->pengguna('kasir6', Peran::Admin, $this->data['cabang']));
    }

    public function test_isolasi_entitas_cabang(): void
    {
        $entitasA = $this->entitasCabang();
        $cabangB = DB::table('cabang')->insertGetId(['kode' => 'FTN-02', 'nama' => 'Fathona FTN-02', 'created_at' => now(), 'updated_at' => now()]);
        $entitasB = EntitasAkuntansi::create([
            'induk_id' => EntitasAkuntansi::query()->where('tipe', 'yayasan')->value('id'),
            'tipe' => 'cabang', 'cabang_id' => $cabangB, 'kode' => 'ENT-02', 'nama' => 'Entitas 02', 'is_aktif' => true,
        ]);
        $kas = $this->akun($entitasA, '1-101', 'aset', 'debit');
        $rekeningB = $this->rekening($entitasB->id, $cabangB, 'Kas B', 'kas', $kas->id);

        $this->expectException(\RuntimeException::class);
        app(KasService::class)->masuk([
            'entitas_id' => $entitasA,
            'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekeningB->id,
            'tanggal' => today()->toDateString(),
            'nominal' => 100000,
            'akun_lawan_id' => $kas->id,
        ], $this->pengguna('kasir7', Peran::Admin, $this->data['cabang']));
    }

    public function test_rekonsiliasi_bank_hitung_selisih(): void
    {
        $entitas = $this->entitasCabang();
        $bank = $this->akun($entitas, '1-102', 'aset', 'debit');
        $rekening = $this->rekening($entitas, $this->data['cabang'], 'Bank BCA', 'bank', $bank->id);
        $pendapatan = $this->akun($entitas, '4-101', 'pendapatan', 'kredit');

        $user = $this->pengguna('kasir8', Peran::Admin, $this->data['cabang']);
        app(KasService::class)->masuk([
            'entitas_id' => $entitas, 'cabang_id' => $this->data['cabang'],
            'rekening_id' => $rekening->id, 'tanggal' => today()->toDateString(),
            'nominal' => 5000000, 'akun_lawan_id' => $pendapatan->id,
        ], $user);

        $rekonsiliasi = app(RekonsiliasiBankService::class)->mulai($rekening->id, today()->year, today()->month, '5000000');
        $selesai = app(RekonsiliasiBankService::class)->selesaikan($rekonsiliasi, $user->id);

        $this->assertSame('selesai', $selesai->status);
        $this->assertSame('5000000.00', $selesai->saldo_buku);
        $this->assertSame('0.00', $selesai->selisih);
    }
}