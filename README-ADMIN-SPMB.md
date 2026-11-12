# Layar admin SPMB (Filament v4)

Paket ini menambah panel admin untuk menjalankan SPMB: mengatur periode, memverifikasi berkas, mencatat nilai,
menetapkan hasil, daftar ulang, dan mengonversi pendaftar menjadi siswa.

**Status:** ditulis tanpa akses ke PHP, PostgreSQL, maupun Filament, dan belum pernah dijalankan. API Filament v4
ditulis dari pengetahuan saya tentang v4, jadi nama method tertentu mungkin perlu disesuaikan dengan versi yang
Anda pasang (lihat Bila ada galat). Jalankan tes sebelum dipakai panitia.

## Berkas yang akan tertimpa

Paket ini memperbarui berkas dari paket sebelumnya. Bila Anda pernah mengubahnya, gabungkan manual:

- `app/Support/CabangKonteks.php`: lingkup cabang kini diturunkan langsung dari pengguna yang login (lihat Keamanan).
- `app/Services/PenugasanPeran.php`: menyegarkan konteks setelah peran berubah.
- `app/Models/User.php`: kini `implements FilamentUser, HasName` (wajib agar Filament bisa dipakai di non-lokal,
  dan karena tabel `users` tidak punya kolom `name`).
- `app/Models/SpmbPendaftar.php`, `SpmbPeriode.php`, `SpmbBerkas.php`: menambah relasi.
- `config/spmb.php`: menambah `peran_panitia`.
- `tests/Concerns/MenyiapkanSpmb.php` dan `tests/Feature/PendaftaranSpmbTest.php`: dukungan banyak cabang; nama
  sekolah uji berubah menjadi `SDIT Fathona FTN-01`.

## Pemasangan

1. Ekstrak ke akar proyek.
2. Tambahkan di `routes/web.php`:
   ```php
   require __DIR__.'/spmb_admin.php';
   ```
3. Pastikan `App\Providers\AksesServiceProvider` terdaftar (mendaftarkan `CabangKonteks` sebagai scoped singleton)
   seperti pada paket F0.
4. Pastikan panel Filament Anda menemukan resource dan widget di `app/Filament/` (bawaan
   `discoverResources(in: app_path('Filament/Resources'), ...)` dan `discoverWidgets(...)`). Grup menu **SPMB**
   muncul otomatis.
5. Peran yang boleh masuk panel diatur di `config/akses.php` (`peran_panel`), dan yang boleh mengelola SPMB di
   `config/spmb.php` (`peran_panitia`). Bawaan: panitia = sysadmin, yayasan, direktur, kepala_cabang,
   kepala_sekolah, admin. `guru`, `orang_tua`, `siswa` tidak boleh masuk panel.
6. `php artisan test`

## Alur kerja panitia

1. **SPMB > Periode SPMB**: buat periode (cabang, sekolah, tahun ajaran, tanggal, kuota, status), lalu di tab
   bawah isi **Jalur**, **Persyaratan berkas**, dan **Tahap seleksi** (dengan bobot). Ubah status menjadi
   **Dibuka**: formulir publik `/spmb` baru menerimanya.
2. **SPMB > Pendaftar**: buka satu pendaftar. Di tab **Berkas persyaratan**, klik Lihat untuk membuka berkas,
   lalu tandai Valid atau Tidak valid (alasan penolakan terlihat oleh pendaftar di halaman cek status).
3. Tombol **Tandai terverifikasi**: hanya berhasil bila semua berkas wajib valid.
4. Tab **Nilai seleksi**: catat nilai per tahap. Skor dihitung rata-rata berbobot dan tampil di bagian
   Seleksi dan hasil. Pencatatan nilai pertama mengubah status menjadi Dalam seleksi.
5. **Tetapkan hasil** (lulus, cadangan, tidak lulus). Di daftar periode, **Hitung peringkat** memberi peringkat
   menurut skor (skor sama = peringkat sama).
6. Yang lulus: **Mulai daftar ulang**, lalu **Daftar ulang selesai** (pilih rombel bila sudah ada), lalu
   **Jadikan siswa** (konversi).
7. **Mengundurkan diri** menandai pendaftar mundur dan kontak CRM-nya tidak lanjut.
8. Hasil seleksi tidak tampil di halaman cek status publik sampai `config('spmb.tampilkan_hasil_di_status')`
   diubah menjadi `true` pada hari pengumuman.

Dashboard menampilkan ringkasan jumlah per tahap (dibatasi ke cabang pengguna). Pendaftar tidak dibuat dari panel;
mereka masuk lewat formulir publik. Biodata yang salah ketik dikoreksi lewat **Koreksi biodata**.

## Keamanan

- **Lingkup cabang kini fail-closed untuk pengguna login.** `CabangKonteks` membaca peran pengguna yang sedang
  login langsung dari database. Admin cabang A hanya melihat data cabang A, pada semua halaman dan semua request
  Livewire, tanpa bergantung pada urutan middleware. Peran pada team 0 (sysadmin, yayasan, direktur) melihat semua
  cabang; pengguna tanpa peran tidak melihat apa pun. Tanpa pengguna login (console, queue, formulir publik)
  lingkup tidak dibatasi. Middleware `TetapkanKonteksCabang` dari paket F0 sekarang tidak wajib.
- **Berkas pendaftar** ada di disk privat dan hanya terbuka lewat `/berkas-spmb/{id}` untuk panitia di cabang
  yang berhak. NIK ditampilkan tersamar (4 digit awal dan 4 digit akhir).
- **Hapus data** (jalur, persyaratan, tahap) hanya untuk sysadmin dan ditolak bila sudah dipakai pendaftar.

## Bila ada galat

Hal yang paling mungkin berbeda dari versi Filament v4 yang Anda pasang:

- `Action::schema()` (untuk tindakan dengan formulir di modal). Bila "method not found", ganti `->schema([...])`
  menjadi `->form([...])` pada `ViewSpmbPendaftar` dan `BerkasRelationManager`.
- Namespace komponen: `Filament\Schemas\Components\Section`, `Filament\Schemas\Components\Utilities\Get/Set`,
  `Filament\Actions\*`, `Filament\Widgets\StatsOverviewWidget\Stat`.
- `recordActions()` pada tabel (di v3 bernama `actions()`).

Kirimkan pesan galat lengkapnya dan saya sesuaikan.

## Belum ada

- Layar CRM prospek (`spmb_kontak`, tindak lanjut, kampanye) dan dashboard corong dari view `v_spmb_*`.
- Aksi massal (verifikasi atau penetapan hasil banyak pendaftar sekaligus) dan ekspor dari panel
  (sementara pakai `php artisan spmb:ekspor`).
- Unggah ulang berkas yang ditolak oleh pendaftar, dan notifikasi WhatsApp.
- Pengaturan penayangan hasil per periode (saat ini satu pengaturan global).
