<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Keuangan\TagihanException;
use App\Models\Akun;
use App\Models\JenisTagihan;
use App\Models\Orang;
use App\Models\RekeningKas;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\TarifTagihan;
use App\Models\User;
use App\Services\Keuangan\SinkronisasiEntitas;
use App\Services\Keuangan\TagihanService;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class TagihanPembayaranTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private int $entitas;
    private int $cabang;
    private int $sekolah;
    private int $tahunAjaran;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());

        $this->data = $this->periodeTerbuka([], 'FTN-01');
        $this->cabang = $this->data['cabang'];
        $this->sekolah = $this->data['sekolah'];
        $this->tahunAjaran = $this->data['tahun'];

        $hasilSinkronisasi = app(SinkronisasiEntitas::class)->jalankan();
        $this->entitas = $hasilSinkronisasi['cabang'][$this->cabang];
    }

    private function pengguna(string $username, Peran $peran): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $this->cabang);

        return $user;
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

    private function siswa(): Siswa
    {
        $orang = Orang::create(['nama_lengkap' => 'Siswa Uji', 'jenis_kelamin' => 'L']);

        return Siswa::create([
            'orang_id' => $orang->id, 'nis_yayasan' => 'SIS001', 'nisn' => '123456789001',
            'tanggal_masuk_yayasan' => today(), // Kolom ini wajib NOT NULL
            'status' => 'aktif',
        ]);
    }

    private function jenisTagihan(string $kode, string $nama, ?int $akunId = null): JenisTagihan
    {
        return JenisTagihan::create([
            'kode' => $kode, 'nama' => $nama, 'tipe' => 'bulanan',
            'akun_pendapatan_id' => $akunId, 'is_aktif' => true,
        ]);
    }

    private function tarif(int $jenisTagihanId, int $sekolahId, int $tahunAjaranId, string $nominal): TarifTagihan
    {
        return TarifTagihan::create([
            'jenis_tagihan_id' => $jenisTagihanId, 'sekolah_id' => $sekolahId,
            'tahun_ajaran_id' => $tahunAjaranId, 'nominal' => $nominal, 'is_aktif' => true,
        ]);
    }

    public function test_tagihan_dibuat_berdasarkan_tarif_dan_diskon(): void
    {
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');

        $s = $this->siswa();
        $tagihan = app(TagihanService::class)->buat([
            'cabang_id' => $this->cabang,
            'entitas_id' => $this->entitas,
            'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran,
            'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id,
            'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(),
        ]);

        $this->assertSame('500000.00', $tagihan->nominal);
        $this->assertSame('0.00', $tagihan->diskon);
        $this->assertSame('500000.00', $tagihan->total);
        $this->assertSame('0.00', $tagihan->terbayar);
        $this->assertSame('belum', $tagihan->status);
    }

    public function test_tidak_bisa_buat_tagihan_duplikat_periode(): void
    {
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');

        $s = $this->siswa();
        $data = [
            'cabang_id' => $this->cabang, 'entitas_id' => $this->entitas, 'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran, 'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id, 'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(), 'periode_label' => 'Januari 2027',
        ];
        app(TagihanService::class)->buat($data);

        $this->expectException(TagihanException::class);
        app(TagihanService::class)->buat($data);
    }

    private function pembayaran(array $data = [])
    {
        return app(\App\Services\Keuangan\PembayaranService::class)->buat(array_merge([
            'cabang_id' => $this->cabang,
            'entitas_id' => $this->entitas,
            'tanggal' => today()->toDateString(),
            'metode' => 'tunai',
            'nominal' => '500000',
            'rekening_id' => $this->rekeningKas()->id,
        ], $data));
    }

    private function rekeningKas(): \App\Models\RekeningKas
    {
        $kas = $this->akun($this->entitas, '1-101', 'aset', 'debit');

        return $this->rekening($this->entitas, $this->cabang, 'Kas Sekolah', 'kas', $kas->id);
    }

    private function siapkanPiutang(): void
    {
        $piutang = $this->akun($this->entitas, '1-103', 'aset', 'debit');
        \App\Models\PemetaanAkun::create([
            'entitas_id' => $this->entitas, 'kunci' => 'piutang_siswa', 'akun_id' => $piutang->id,
        ]);
    }

    public function test_pembayaran_berhasil_dialokasi_ke_tagihan(): void
    {
        $this->siapkanPiutang();
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');
        $s = $this->siswa();

        $tagihan = app(TagihanService::class)->buat([
            'cabang_id' => $this->cabang, 'entitas_id' => $this->entitas, 'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran, 'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id, 'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(),
        ]);

        $bayar = $this->pembayaran(['siswa_id' => $s->id, 'nominal' => '500000']);
        $user = $this->pengguna('kasir', Peran::Admin);

        $hasil = app(\App\Services\Keuangan\PembayaranService::class)->konfirmasi($bayar, $user);

        $this->assertSame('berhasil', $hasil->status);
        $this->assertNotNull($hasil->transaksi_kas_id);
        $this->assertDatabaseHas('transaksi_kas', ['id' => $hasil->transaksi_kas_id, 'entitas_id' => $this->entitas]);

        $this->assertSame('500000.00', $tagihan->fresh()->terbayar);
        $this->assertSame('lunas', $tagihan->fresh()->status);
        $this->assertSame(1, \App\Models\PembayaranAlokasi::query()->where('pembayaran_id', $bayar->id)->count());
    }

    public function test_pembayaran_sebagian(): void
    {
        $this->siapkanPiutang();
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');
        $s = $this->siswa();

        app(TagihanService::class)->buat([
            'cabang_id' => $this->cabang, 'entitas_id' => $this->entitas, 'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran, 'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id, 'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(),
        ]);

        $bayar = $this->pembayaran(['siswa_id' => $s->id, 'nominal' => '200000']);
        app(\App\Services\Keuangan\PembayaranService::class)->konfirmasi($bayar, $this->pengguna('kasir', Peran::Admin));

        $this->assertSame('200000.00', \App\Models\Tagihan::query()->where('siswa_id', $s->id)->value('terbayar'));
        $this->assertSame('sebagian', \App\Models\Tagihan::query()->where('siswa_id', $s->id)->value('status'));
    }

    public function test_pembayaran_berlebih_menjadi_kelebihan_bayar(): void
    {
        $this->siapkanPiutang();
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');
        $s = $this->siswa();

        app(TagihanService::class)->buat([
            'cabang_id' => $this->cabang, 'entitas_id' => $this->entitas, 'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran, 'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id, 'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(),
        ]);

        $bayar = $this->pembayaran(['siswa_id' => $s->id, 'nominal' => '700000']);
        app(\App\Services\Keuangan\PembayaranService::class)->konfirmasi($bayar, $this->pengguna('kasir', Peran::Admin));

        $this->assertSame('500000.00', \App\Models\Tagihan::query()->where('siswa_id', $s->id)->value('terbayar'));
        $this->assertSame('lunas', \App\Models\Tagihan::query()->where('siswa_id', $s->id)->value('status'));
        $this->assertSame('500000.00', \App\Models\PembayaranAlokasi::query()->where('pembayaran_id', $bayar->id)->sum('nominal'));
    }

    public function test_pembayaran_dibatalkan_menjadi_void_transaksi_kas(): void
    {
        $this->siapkanPiutang();
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = $this->jenisTagihan('SPP', 'SPP Bulanan', $pendapatan->id);
        $tarif = $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '500000');
        $s = $this->siswa();
        $user = $this->pengguna('kasir', Peran::Admin);

        app(TagihanService::class)->buat([
            'cabang_id' => $this->cabang, 'entitas_id' => $this->entitas, 'siswa_id' => $s->id,
            'tahun_ajaran_id' => $this->tahunAjaran, 'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id, 'tanggal_terbit' => today()->toDateString(),
            'jatuh_tempo' => today()->addDays(7)->toDateString(),
        ]);
        $bayar = $this->pembayaran(['siswa_id' => $s->id, 'nominal' => '500000']);
        app(\App\Services\Keuangan\PembayaranService::class)->konfirmasi($bayar, $user);

        $hasil = app(\App\Services\Keuangan\PembayaranService::class)->batalkan($bayar, $user, 'Kesalahan pencatatan');

        $this->assertSame('batal', $hasil->status);
        $this->assertSame('batal', \App\Models\TransaksiKas::query()->where('id', $hasil->transaksi_kas_id)->value('status'));
    }

    public function test_rekening_bukan_miliki_entitas_ditolak(): void
    {
        $this->siapkanPiutang();

        // Buat cabang dan entitas yang benar-benar berbeda
        $cabangB = \Illuminate\Support\Facades\DB::table('cabang')->insertGetId([
            'kode' => 'FTN-02', 'nama' => 'Cabang B',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $entitasB = \Illuminate\Support\Facades\DB::table('entitas_akuntansi')->insertGetId([
            'cabang_id' => $cabangB,
            'tipe' => 'cabang',
            'kode' => 'ENT-B', 'nama' => 'Entitas B',
            'is_aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $kasB = $this->akun($entitasB, '1-102', 'aset', 'debit');
        $rekeningB = $this->rekening($entitasB, $cabangB, 'Kas B', 'kas', $kasB->id);

        $s = $this->siswa();

        $bayar = $this->pembayaran(['rekening_id' => $rekeningB->id, 'siswa_id' => $s->id]);

        $this->expectException(\RuntimeException::class);
        app(\App\Services\Keuangan\PembayaranService::class)->konfirmasi($bayar, $this->pengguna('kasir', Peran::Admin));
    }

    public function test_gateway_log_idempoten_berdasarkan_referensi(): void
    {
        \App\Models\PembayaranGatewayLog::create([
            'penyedia' => 'uji', 'referensi' => 'REF-001', 'payload' => ['a' => 1],
            'status_proses' => 'baru', 'diterima_pada' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\PembayaranGatewayLog::create([
            'penyedia' => 'uji', 'referensi' => 'REF-001', 'payload' => ['a' => 2],
            'status_proses' => 'baru', 'diterima_pada' => now(),
        ]);
    }

    public function test_buat_daftar_ulang_dan_idemen(): void
    {
        $pendapatan = $this->akun($this->entitas, '4-101', 'pendapatan', 'kredit');
        $jenis = \App\Models\JenisTagihan::create([
            'kode' => 'daftar_ulang', 'nama' => 'Daftar Ulang', 'tipe' => 'sekali',
            'akun_pendapatan_id' => $pendapatan->id, 'is_aktif' => true,
        ]);
        $this->tarif($jenis->id, $this->sekolah, $this->tahunAjaran, '1000000');

        $s = $this->siswa();
        $cabang = \App\Models\Cabang::find($this->cabang);
        $sekolah = \App\Models\Sekolah::find($this->sekolah);
        $tahunAjaran = \App\Models\TahunAjaran::find($this->tahunAjaran);
        $entitas = \App\Models\EntitasAkuntansi::find($this->entitas);

        $tagihan1 = app(TagihanService::class)->buatDaftarUlang($s, $entitas, $cabang, $sekolah, $tahunAjaran);
        $tagihan2 = app(TagihanService::class)->buatDaftarUlang($s, $entitas, $cabang, $sekolah, $tahunAjaran);

        $this->assertNotNull($tagihan1);
        $this->assertSame('daftar_ulang', $tagihan1->jenisTagihan->kode);
        $this->assertSame('1000000.00', $tagihan1->total);
        $this->assertSame($tagihan1->id, $tagihan2->id);
        $this->assertSame(1, \App\Models\Tagihan::query()->where('siswa_id', $s->id)->count());
    }

    public function test_buat_daftar_ulang_kembali_null_bila_tidak_terkonfigurasi(): void
    {
        $s = $this->siswa();
        $entitas = \App\Models\EntitasAkuntansi::find($this->entitas);
        $cabang = \App\Models\Cabang::find($this->cabang);
        $sekolah = \App\Models\Sekolah::find($this->sekolah);
        $tahunAjaran = \App\Models\TahunAjaran::find($this->tahunAjaran);

        $this->assertNull(app(TagihanService::class)->buatDaftarUlang($s, $entitas, $cabang, $sekolah, $tahunAjaran));
    }
}
