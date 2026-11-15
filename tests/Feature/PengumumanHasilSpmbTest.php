<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\AntreanPesan;
use App\Models\Orang;
use App\Models\SpmbHasil;
use App\Models\SpmbPendaftar;
use App\Models\User;
use App\Services\PenugasanPeran;
use App\Services\Spmb\PendaftaranSpmb;
use App\Services\Spmb\PengumumanHasilSpmb;
use Database\Seeders\PeranSeeder;
use Database\Seeders\TemplatPesanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class PengumumanHasilSpmbTest extends TestCase
{
    use MenyiapkanSpmb, RefreshDatabase;

    private int $penetapId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->seed(PeranSeeder::class);
        $this->seed(TemplatPesanSeeder::class);
        Storage::fake('local');
        config(['pesan.aktif' => true, 'pesan.jam_tenang' => ['mulai' => null, 'selesai' => null]]);
        $this->penetapId = (int) $this->pengguna(null, Peran::Sysadmin, 'penetap')->id;
    }

    private function siapkan(string $kode = 'FTN-01'): array
    {
        $s = $this->periodeTerbuka([], $kode);
        DB::table('spmb_tahap_seleksi')->insert([
            'periode_id' => $s['periode']->id, 'nama' => 'Tes', 'urutan' => 1, 'bobot' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $s;
    }

    private function pendaftar(array $s, string $nama = 'Aisyah Putri'): SpmbPendaftar
    {
        return app(PendaftaranSpmb::class)->daftar(
            $s['periode'], $this->dataPendaftaran($s, [
                'nama_lengkap' => $nama,
                'nik' => '1671'.str_pad((string) abs(crc32($nama)), 12, '0', STR_PAD_LEFT),
            ]),
            [$s['kk'] => UploadedFile::fake()->image('kk.jpg'), $s['akta'] => UploadedFile::fake()->image('akta.jpg')],
        );
    }

    private function hasil(SpmbPendaftar $p, string $keputusan, ?string $status = null): void
    {
        SpmbHasil::create([
            'pendaftar_id' => $p->id, 'keputusan' => $keputusan,
            'tanggal_keputusan' => today(), 'ditetapkan_oleh' => $this->penetapId,
        ]);
        $p->update(['status' => $status ?? $keputusan]);
    }

    private function pengguna(?int $cabangId, Peran $peran, string $username): User
    {
        $orang = Orang::create(['nama_lengkap' => 'Pengguna '.$username, 'jenis_kelamin' => 'L']);
        $user = User::create(['orang_id' => $orang->id, 'username' => $username, 'password' => 'rahasia-uji-123']);
        app(PenugasanPeran::class)->berikan($user, $peran, $cabangId);

        return $user;
    }

    private function layanan(): PengumumanHasilSpmb
    {
        if (auth()->guest()) {
            $this->actingAs($this->pengguna(null, Peran::Sysadmin, 'sysadmin'));
        }

        return app(PengumumanHasilSpmb::class);
    }

    private function umumkan(array $s): int
    {
        return $this->layanan()->umumkan($s['periode']->fresh(), auth()->id() ?? 1);
    }

    public function test_hasil_tidak_bocor_sebelum_pengumuman_dan_tampil_setelahnya(): void
    {
        $s = $this->siapkan();
        $p = $this->pendaftar($s);
        $this->hasil($p, 'lulus');
        $data = ['nomor_pendaftaran' => $p->nomor_pendaftaran, 'tanggal_lahir' => '2020-03-15'];

        $this->post(route('spmb.status.cek'), $data)->assertOk()
            ->assertSee('akan diumumkan')->assertDontSee('Dinyatakan lulus');
        $this->assertSame(0, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_hasil:%')->count());

        $jumlah = $this->umumkan($s);

        $this->assertSame(1, $jumlah);
        $this->assertNotNull($s['periode']->fresh()->hasil_diumumkan_pada);
        $this->assertDatabaseHas('antrean_pesan', ['kunci_unik' => "spmb_hasil:{$p->id}:lulus"]);
        $this->post(route('spmb.status.cek'), $data)->assertOk()->assertSee('Dinyatakan lulus');
    }

    public function test_satu_pesan_untuk_setiap_hasil_dan_pengulangan_ditolak(): void
    {
        $s = $this->siapkan();
        $id = [];
        foreach (['lulus', 'cadangan', 'tidak_lulus'] as $i => $status) {
            $p = $this->pendaftar($s, 'Calon '.$i);
            $this->hasil($p, $status);
            $id[$status] = $p->id;
        }
        $p = $this->pendaftar($s, 'Daftar Ulang');
        $this->hasil($p, 'lulus', 'daftar_ulang');
        $id['daftar_ulang'] = $p->id;

        $this->assertSame(4, $this->umumkan($s));
        $this->assertSame(4, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_hasil:%')->count());
        foreach ($id as $status => $pendaftarId) {
            $keputusan = $status === 'daftar_ulang' ? 'lulus' : $status;
            $this->assertDatabaseHas('antrean_pesan', ['kunci_unik' => "spmb_hasil:{$pendaftarId}:{$keputusan}"]);
        }

        $this->expectException(TransisiTidakValid::class);
        $this->expectExceptionMessage('sudah diumumkan');
        $this->layanan()->umumkan($s['periode']->fresh(), auth()->id());
    }

    public function test_periode_lain_dan_pendaftar_tanpa_hasil_tidak_ikut_diumumkan(): void
    {
        $a = $this->siapkan('FTN-01');
        $b = $this->siapkan('FTN-02');
        $p = $this->pendaftar($a);
        $this->hasil($p, 'lulus');
        $this->pendaftar($a, 'Belum Selesai');
        $q = $this->pendaftar($b);
        $this->hasil($q, 'lulus');

        $this->assertSame(1, $this->umumkan($a));

        $this->assertNull($b['periode']->fresh()->hasil_diumumkan_pada);
        $this->assertDatabaseMissing('antrean_pesan', ['kunci_unik' => "spmb_hasil:{$q->id}:lulus"]);
        $this->assertSame(1, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_hasil:%')->count());
    }

    public function test_templat_nonaktif_tidak_menggagalkan_pencatatan_pengumuman(): void
    {
        $s = $this->siapkan();
        $p = $this->pendaftar($s);
        $this->hasil($p, 'lulus');
        DB::table('templat_pesan')->where('kode', 'spmb_hasil_lulus')->update(['is_aktif' => false]);

        $this->assertSame(0, $this->umumkan($s));

        $this->assertNotNull($s['periode']->fresh()->hasil_diumumkan_pada);
        $this->assertDatabaseMissing('antrean_pesan', ['kunci_unik' => "spmb_hasil:{$p->id}:lulus"]);
    }

    public function test_pengguna_cabang_lain_tidak_dapat_mengumumkan(): void
    {
        $a = $this->siapkan('FTN-01');
        $b = $this->siapkan('FTN-02');
        $p = $this->pendaftar($a);
        $this->hasil($p, 'lulus');
        $user = $this->pengguna($b['cabang'], Peran::KepalaCabang, 'kacabb');
        $this->actingAs($user);

        try {
            app(PengumumanHasilSpmb::class)->umumkan($a['periode'], (int) $user->id);
            $this->fail('Pengumuman lintas cabang seharusnya ditolak.');
        } catch (TransisiTidakValid $e) {
            $this->assertSame('Periode SPMB tidak ditemukan atau berada di luar cabang Anda.', $e->getMessage());
        }

        $this->assertNull($a['periode']->fresh()->hasil_diumumkan_pada);
        $this->assertSame(0, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_hasil:%')->count());
    }

    public function test_periode_tanpa_hasil_dan_pengumuman_ulang_tidak_mengubah_data(): void
    {
        $s = $this->siapkan();

        try {
            $this->layanan()->umumkan($s['periode'], 1);
            $this->fail('Periode tanpa hasil seharusnya ditolak.');
        } catch (TransisiTidakValid $e) {
            $this->assertSame('Belum ada hasil seleksi yang dapat diumumkan pada periode ini.', $e->getMessage());
        }

        $this->assertNull($s['periode']->fresh()->hasil_diumumkan_pada);
    }

    public function test_admin_tidak_memiliki_kewenangan_mengumumkan_meski_di_cabang_sendiri(): void
    {
        $s = $this->siapkan();
        $p = $this->pendaftar($s);
        $this->hasil($p, 'lulus');
        $user = $this->pengguna($s['cabang'], Peran::Admin, 'admin');
        $this->actingAs($user);

        try {
            $this->layanan()->umumkan($s['periode'], (int) $user->id);
            $this->fail('Admin tidak seharusnya dapat mengumumkan hasil.');
        } catch (TransisiTidakValid $e) {
            $this->assertSame('Anda tidak memiliki kewenangan untuk mengumumkan hasil seleksi.', $e->getMessage());
        }

        $this->assertNull($s['periode']->fresh()->hasil_diumumkan_pada);
        $this->assertSame(0, AntreanPesan::query()->where('kunci_unik', 'like', 'spmb_hasil:%')->count());
    }

    public function test_ringkasan_hanya_menghitung_hasil_yang_berhak_diumumkan(): void
    {
        $s = $this->siapkan();
        $p = $this->pendaftar($s);
        $this->hasil($p, 'lulus', 'mundur');
        $q = $this->pendaftar($s, 'Daftar Ulang');
        $this->hasil($q, 'lulus', 'daftar_ulang');
        $this->pendaftar($s, 'Tanpa Hasil');

        $this->assertSame(
            ['lulus' => 1, 'cadangan' => 0, 'tidak_lulus' => 0],
            PengumumanHasilSpmb::ringkasan($s['periode']),
        );
    }
}
