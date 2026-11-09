# F1: SPMB

Tambahan untuk proyek F0. Ekstrak ke akar proyek Laravel; dua berkas lama akan tertimpa:
`app/Models/Orang.php` (kini memakai trait `MemilikiNikTerenkripsi`, perilakunya sama) dan
`database/seeders/ReferensiSeeder.php` (tambah kategori `spmb_sumber` dan `spmb_kanal_kampanye`).

Lalu jalankan:

    php artisan migrate
    php artisan db:seed --class=ReferensiSeeder
    php artisan test --filter="NikTest|PenomoranTest|SpmbTest"

Status: ditulis tanpa akses ke PHP dan PostgreSQL, belum pernah dijalankan. Tes di sini adalah cara
memverifikasinya; laporkan galat apa pun.

## Isi

- `2026_11_05_000100_buat_tabel_spmb_prospek`: periode, jalur, persyaratan berkas, kampanye, kontak (CRM),
  tindak lanjut, kegiatan, peserta kegiatan.
- `2026_11_05_000200_buat_tabel_spmb_pendaftaran`: pendaftar, wali pendaftar, berkas, tahap seleksi,
  nilai seleksi, hasil, daftar ulang.
- `2026_11_05_000300_buat_view_spmb`: `v_spmb_corong`, `v_spmb_kinerja_petugas`, `v_spmb_roi_kampanye`.
- `PenomoranDokumen` (service), `Telepon` (helper), trait `MemilikiNikTerenkripsi`, model `SpmbPeriode`,
  `SpmbKontak`, `SpmbPendaftar`.

## Penyimpangan dari dokumen skema v0.2

1. **Unik kontak.** Dokumen menulis unik parsial `(telepon, tahun_ajaran_id)`. Itu akan menolak orang tua yang
   mendaftarkan dua anak, dan menghalangi satu nomor menghubungi dua cabang. Diganti menjadi unik
   `(cabang_id, tahun_ajaran_id, telepon, nama_calon_siswa tanpa beda huruf besar-kecil)`. Nomor
   dinormalkan ke bentuk 62812xxxx sebelum disimpan.
2. **NIK pendaftar** terenkripsi dengan `nik_hash`, sama seperti `orang`; unik per periode, bukan global.
3. **Integritas lewat FK komposit:** periode selalu sepasang dengan sekolah dan cabang yang benar; jalur,
   persyaratan, tahap, dan nilai seleksi harus berasal dari periode pendaftarnya. `spmb_berkas` dan
   `spmb_nilai_seleksi` karena itu menyimpan `periode_id` juga.
4. `spmb_tahap_seleksi.ujian_id` belum punya FK karena tabel `ujian` baru ada di fase F4.
5. Tagihan pendaftaran (`tagihan.pendaftar_id`) menunggu modul Keuangan (F2).

## Catatan pemakaian

- Nomor pendaftaran dibuat otomatis (`SPMB/{kode cabang}/{tahun}/{urut 4 digit}`) lewat tabel `penomoran`.
  Pola dapat diubah dengan mengedit baris `penomoran` untuk kode `spmb_pendaftar`.
- View bypass global scope Eloquent. Saat membacanya, saring selalu dengan `cabang_id` yang boleh diakses
  pengguna (`app(CabangKonteks::class)->cabangDiizinkan()`).
- Corong hanya menghitung pendaftar yang berasal dari kontak. Pendaftar yang mendaftar langsung lewat formulir
  tanpa kontak tidak masuk `v_spmb_corong`.
- Konversi pendaftar menjadi siswa (membuat `orang`, `siswa`, `siswa_sekolah`, `siswa_wali`, `rombel_siswa`
  dalam satu transaksi) belum ada; itu logika service dan dikerjakan bersama formulir pendaftarannya.
