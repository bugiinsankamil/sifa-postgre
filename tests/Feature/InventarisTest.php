<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\KategoriAset;
use App\Models\Orang;
use App\Models\Ruang;
use App\Models\User;
use App\Services\Inventaris\TransaksiStok;
use App\Services\PenugasanPeran;
use Database\Seeders\PeranSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MenyiapkanSpmb;
use Tests\TestCase;

class InventarisTest extends TestCase
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

    public function test_admin_cabang_melihat_gudang_cabangnya_saja(): void
    {
        Gudang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'G-01',
            'nama' => 'Gudang Utama', 'is_aktif' => true,
        ]);
        Gudang::create([
            'cabang_id' => $this->b['cabang'], 'kode' => 'G-02',
            'nama' => 'Gudang Cabang 2', 'is_aktif' => true,
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Inventaris\GudangResource::getUrl('index'))
            ->assertOk()
            ->assertSee('G-01')
            ->assertDontSee('G-02');
    }

    public function test_admin_cabang_melihat_ruang_cabangnya_saja(): void
    {
        Ruang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'R-01',
            'nama' => 'Ruang Lab', 'kondisi' => 'baik', 'status' => 'aktif',
        ]);
        Ruang::create([
            'cabang_id' => $this->b['cabang'], 'kode' => 'R-02',
            'nama' => 'Ruang Kelas', 'kondisi' => 'baik', 'status' => 'aktif',
        ]);

        $this->actingAs($this->pengguna('admin1', Peran::Admin, $this->a['cabang']))
            ->get(\App\Filament\Resources\Inventaris\RuangResource::getUrl('index'))
            ->assertOk()
            ->assertSee('R-01')
            ->assertDontSee('R-02');
    }

    public function test_transaksi_stok_menambah_dan_mengurangi_stok(): void
    {
        $kategori = KategoriAset::create(['kode' => 'HP', 'nama' => 'Habis Pakai', 'jenis' => 'habis_pakai']);
        $barang = Barang::create([
            'kode' => 'ATK-001', 'nama' => 'Kertas A4', 'kategori_id' => $kategori->id,
            'jenis' => 'habis_pakai', 'satuan_ref' => 'rim', 'is_aktif' => true,
        ]);
        $gudang = Gudang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'G-01',
            'nama' => 'Gudang Utama', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(TransaksiStok::class);

        // Masuk 10 rim
        $service->mutasi($gudang->id, $barang->id, 10, 'masuk', 50000, 'Manual', 1, $user->id);
        $this->assertSame(10, $service->saldo($gudang->id, $barang->id));

        // Keluar 3 rim
        $service->mutasi($gudang->id, $barang->id, -3, 'keluar', null, 'Manual', 2, $user->id);
        $this->assertSame(7, $service->saldo($gudang->id, $barang->id));
    }

    public function test_transaksi_stok_tidak_boleh_minus(): void
    {
        $kategori = KategoriAset::create(['kode' => 'HP', 'nama' => 'Habis Pakai', 'jenis' => 'habis_pakai']);
        $barang = Barang::create([
            'kode' => 'ATK-001', 'nama' => 'Kertas A4', 'kategori_id' => $kategori->id,
            'jenis' => 'habis_pakai', 'satuan_ref' => 'rim', 'is_aktif' => true,
        ]);
        $gudang = Gudang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'G-01',
            'nama' => 'Gudang Utama', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(TransaksiStok::class);

        // Masuk 5 rim
        $service->mutasi($gudang->id, $barang->id, 5, 'masuk', 50000, 'Manual', 1, $user->id);

        // Coba keluar 10 rim (lebih dari stok)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stok tidak mencukupi');
        $service->mutasi($gudang->id, $barang->id, -10, 'keluar', null, 'Manual', 2, $user->id);
    }

    public function test_transfer_stok_antar_gudang(): void
    {
        $kategori = KategoriAset::create(['kode' => 'HP', 'nama' => 'Habis Pakai', 'jenis' => 'habis_pakai']);
        $barang = Barang::create([
            'kode' => 'ATK-001', 'nama' => 'Kertas A4', 'kategori_id' => $kategori->id,
            'jenis' => 'habis_pakai', 'satuan_ref' => 'rim', 'is_aktif' => true,
        ]);
        $gudang1 = Gudang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'G-01',
            'nama' => 'Gudang Utama', 'is_aktif' => true,
        ]);
        $gudang2 = Gudang::create([
            'cabang_id' => $this->a['cabang'], 'kode' => 'G-02',
            'nama' => 'Gudang Sekolah', 'is_aktif' => true,
        ]);

        $user = $this->pengguna('admin1', Peran::Admin, $this->a['cabang']);
        $service = app(TransaksiStok::class);

        // Masuk 10 rim di gudang 1
        $service->mutasi($gudang1->id, $barang->id, 10, 'masuk', 50000, 'Manual', 1, $user->id);

        // Transfer 4 rim dari gudang 1 ke gudang 2
        $service->transfer($gudang1->id, $gudang2->id, $barang->id, 4, 'Manual', 1, $user->id);

        $this->assertSame(6, $service->saldo($gudang1->id, $barang->id));
        $this->assertSame(4, $service->saldo($gudang2->id, $barang->id));
    }
}
