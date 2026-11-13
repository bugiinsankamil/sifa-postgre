<?php

namespace App\Http\Controllers\Spmb;

use App\Exceptions\Spmb\PendaftaranDitutup;
use App\Exceptions\Spmb\PendaftarGanda;
use App\Exceptions\Spmb\UnggahUlangDitolak;
use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarSpmbRequest;
use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPeriode;
use App\Services\Spmb\PendaftaranSpmb;
use App\Services\Spmb\PerbaikanBerkasSpmb;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PendaftaranSpmbController extends Controller
{
    /** Lama (detik) pendaftar tetap dikenali setelah memasukkan nomor pendaftaran dan tanggal lahir. */
    private const MASA_SESI_STATUS = 1800;

    private const LABEL_STATUS = [
        'draft' => 'Pendaftaran belum selesai.',
        'terdaftar' => 'Pendaftaran diterima dan menunggu verifikasi berkas.',
        'diverifikasi' => 'Berkas sudah diverifikasi. Tunggu informasi jadwal seleksi.',
        'seleksi' => 'Sedang dalam proses seleksi.',
        'daftar_ulang' => 'Dalam proses daftar ulang.',
        'mundur' => 'Pendaftaran dinyatakan mengundurkan diri.',
        'menjadi_siswa' => 'Sudah terdaftar sebagai siswa.',
    ];

    private const STATUS_HASIL = [
        'lulus' => 'Dinyatakan lulus.',
        'cadangan' => 'Masuk daftar cadangan.',
        'tidak_lulus' => 'Dinyatakan tidak lulus.',
    ];

    public function index(): View
    {
        $periode = DB::table('spmb_periode as p')
            ->join('sekolah as s', 's.id', '=', 'p.sekolah_id')
            ->join('cabang as c', 'c.id', '=', 'p.cabang_id')
            ->join('jenjang as j', 'j.id', '=', 's.jenjang_id')
            ->where('p.status', 'dibuka')
            ->whereDate('p.tanggal_buka', '<=', today())
            ->whereDate('p.tanggal_tutup', '>=', today())
            ->orderBy('c.nama')->orderBy('j.urutan')->orderBy('p.gelombang')
            ->get(['p.id', 'p.nama as periode', 'p.gelombang', 'p.tanggal_tutup', 's.nama as sekolah', 'c.nama as cabang', 'j.kode as jenjang']);

        return view('spmb.index', ['periode' => $periode->groupBy('cabang')]);
    }

    public function form(SpmbPeriode $periode, PendaftaranSpmb $layanan): View
    {
        $info = $this->info($periode);

        if (! $layanan->terbuka($periode)) {
            return view('spmb.tutup', ['info' => $info]);
        }

        return view('spmb.form', [
            'periode' => $periode,
            'info' => $info,
            'jalur' => DB::table('spmb_jalur')->where('periode_id', $periode->id)->where('is_aktif', true)->orderBy('nama')->get(),
            'persyaratan' => DB::table('spmb_persyaratan_berkas')->where('periode_id', $periode->id)->orderBy('urutan')->get(),
            'agama' => $this->referensi('agama'),
            'pekerjaan' => $this->referensi('pekerjaan'),
            'penghasilan' => $this->referensi('penghasilan'),
        ]);
    }

    public function store(DaftarSpmbRequest $request, SpmbPeriode $periode, PendaftaranSpmb $layanan): RedirectResponse
    {
        try {
            $pendaftar = $layanan->daftar(
                $periode,
                $request->safe()->except(['situs', 'berkas', 'persetujuan']),
                $request->file('berkas', [])
            );
        } catch (PendaftaranDitutup|PendaftarGanda $e) {
            return back()->withInput()->withErrors(['umum' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['umum' => 'Data calon siswa ini sudah tercatat pada periode ini.']);
        }

        return redirect()->route('spmb.terdaftar')->with('nomor', $pendaftar->nomor_pendaftaran);
    }

    public function terdaftar(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('nomor')) {
            return redirect()->route('spmb.index');
        }

        return view('spmb.terdaftar', ['nomor' => $request->session()->get('nomor')]);
    }

    public function statusForm(): View
    {
        return view('spmb.status', ['dicari' => false, 'pendaftar' => null]);
    }

    public function statusCek(Request $request): View
    {
        $data = $request->validate([
            'nomor_pendaftaran' => ['required', 'string', 'max:40'],
            'tanggal_lahir' => ['required', 'date'],
        ]);

        $pendaftar = SpmbPendaftar::query()->withoutGlobalScopes()
            ->where('nomor_pendaftaran', trim($data['nomor_pendaftaran']))
            ->whereDate('tanggal_lahir', $data['tanggal_lahir'])
            ->first();

        if ($pendaftar === null) {
            return view('spmb.status', ['dicari' => true, 'pendaftar' => null]);
        }

        // Pendaftar dikenali selama sesi ini, supaya bisa mengunggah ulang berkas tanpa mengisi ulang data
        $request->session()->put('spmb.pendaftar_id', $pendaftar->id);
        $request->session()->put('spmb.waktu', now()->timestamp);

        return $this->tampilStatus($pendaftar);
    }

    /** Status pendaftar yang sedang dikenali oleh sesi (setelah cek status atau unggah ulang). */
    public function statusSaya(Request $request): View|RedirectResponse
    {
        $pendaftar = $this->pendaftarDariSesi($request);

        return $pendaftar === null ? redirect()->route('spmb.status') : $this->tampilStatus($pendaftar);
    }

    public function unggahUlang(Request $request, SpmbBerkas $berkas, PerbaikanBerkasSpmb $layanan): RedirectResponse
    {
        $pendaftar = $this->pendaftarDariSesi($request);
        if ($pendaftar === null) {
            return redirect()->route('spmb.status')
                ->withErrors(['umum' => 'Sesi habis. Masukkan kembali nomor pendaftaran dan tanggal lahir.']);
        }

        // Berkas harus milik pendaftar yang dikenali sesi ini
        abort_unless((int) $berkas->pendaftar_id === (int) $pendaftar->id, 403);

        $validator = Validator::make($request->all(), [
            'berkas' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('spmb.berkas_maks_kb', 3072)],
        ], [
            'required' => 'Pilih berkas yang akan diunggah.',
            'file' => 'Berkas tidak valid.',
            'mimes' => 'Berkas harus berformat JPG, PNG, atau PDF.',
            'max' => 'Ukuran berkas maksimal :max KB.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('spmb.status.saya')->withErrors($validator);
        }

        try {
            $layanan->ganti($berkas, $request->file('berkas'));
        } catch (UnggahUlangDitolak $e) {
            return redirect()->route('spmb.status.saya')->withErrors(['umum' => $e->getMessage()]);
        }

        return redirect()->route('spmb.status.saya')->with('sukses', 'Berkas perbaikan diterima dan menunggu verifikasi ulang.');
    }

    private function pendaftarDariSesi(Request $request): ?SpmbPendaftar
    {
        $id = $request->session()->get('spmb.pendaftar_id');
        $waktu = (int) $request->session()->get('spmb.waktu', 0);

        if (! $id || now()->timestamp - $waktu > self::MASA_SESI_STATUS) {
            return null;
        }

        return SpmbPendaftar::query()->withoutGlobalScopes()->find($id);
    }

    private function tampilStatus(SpmbPendaftar $pendaftar): View
    {
        $status = $pendaftar->status;

        if (isset(self::STATUS_HASIL[$status])) {
            $keterangan = config('spmb.tampilkan_hasil_di_status')
                ? self::STATUS_HASIL[$status]
                : 'Hasil seleksi akan diumumkan oleh sekolah.';
        } else {
            $keterangan = self::LABEL_STATUS[$status] ?? 'Status sedang diproses.';
        }

        $berkas = DB::table('spmb_berkas as b')
            ->join('spmb_persyaratan_berkas as r', 'r.id', '=', 'b.persyaratan_id')
            ->where('b.pendaftar_id', $pendaftar->id)
            ->orderBy('r.urutan')
            ->get(['b.id', 'r.nama', 'b.status_verifikasi', 'b.catatan']);

        return view('spmb.status', [
            'dicari' => true,
            'pendaftar' => $pendaftar,
            'keterangan' => $keterangan,
            'berkas' => $berkas,
            'bisaUnggah' => in_array($status, ['draft', 'terdaftar'], true),
        ]);
    }

    private function info(SpmbPeriode $periode): object
    {
        return DB::table('sekolah as s')
            ->join('cabang as c', 'c.id', '=', 's.cabang_id')
            ->join('jenjang as j', 'j.id', '=', 's.jenjang_id')
            ->where('s.id', $periode->sekolah_id)
            ->first(['s.nama as sekolah', 'c.nama as cabang', 'j.nama as jenjang']);
    }

    /** @return array<string, string> kode => label */
    private function referensi(string $kategori): array
    {
        return DB::table('referensi')->where('kategori', $kategori)->where('is_aktif', true)
            ->orderBy('urutan')->pluck('label', 'kode')->all();
    }
}
