# F1: Formulir pendaftaran SPMB dan konversi pendaftar menjadi siswa

Tambahan di atas paket F0 dan migrasi SPMB. Ekstrak ke akar proyek Laravel 11/12.

**Status:** ditulis tanpa akses ke PHP dan PostgreSQL, belum pernah dijalankan. Jalankan tes (langkah 4) sebelum
membuka formulir ke publik, dan laporkan galat apa pun.

## Pemasangan

1. Tambahkan di `routes/web.php`:
   ```php
   require __DIR__.'/spmb.php';
   ```
2. `php artisan migrate` (menambah kolom `spmb_pendaftar_wali.jenis_kelamin` dan unique index anti-ganda;
   migrasi gagal bila sudah ada pendaftar ganda pada periode yang sama: bersihkan dulu).
3. Pastikan `ReferensiSeeder` sudah dijalankan (formulir mengambil agama, pekerjaan, penghasilan dari tabel `referensi`).
4. `php artisan test --filter="PendaftaranSpmbTest|KonversiPendaftarTest"`
5. Siapkan data periode: buat baris `spmb_periode` (status `dibuka`, tanggal buka dan tutup), minimal satu `spmb_jalur`
   yang aktif, dan baris `spmb_persyaratan_berkas`. Belum ada layar admin untuk ini (lihat Belum ada).
6. Buka `/spmb`. Alamat langsung per periode: `/spmb/{id periode}`. Cek status: `/spmb/status`.

## Isi

- Formulir publik (Blade, tanpa dependensi tambahan, nyaman di ponsel): daftar periode, formulir, halaman nomor
  pendaftaran, dan cek status (nomor pendaftaran + tanggal lahir).
- `PendaftaranSpmb`: menyimpan pendaftar, wali, berkas, dan menautkan ke kontak CRM dalam satu transaksi.
- `KonversiPendaftar`: pendaftar menjadi `orang`, `siswa`, `siswa_sekolah`, `siswa_wali`, dan `rombel_siswa`.
- Perintah artisan `spmb:konversi` dan `spmb:ekspor`.
- Tes: `PendaftaranSpmbTest` (12 tes) dan `KonversiPendaftarTest` (8 tes).

## Perilaku yang perlu Anda ketahui

**Formulir**
- Hanya periode berstatus `dibuka` dan dalam rentang tanggalnya yang menerima pendaftaran.
- Setiap pendaftar otomatis punya kontak di CRM. Bila nomor kontak utama dan nama anak sudah ada di
  `spmb_kontak`, kontak itu dipakai ulang dan statusnya menjadi `mendaftar`; bila tidak, dibuat kontak baru
  dengan sumber `website` (`config/spmb.php`). Dengan begitu `v_spmb_corong` mencakup semua pendaftar.
- Pendaftar ganda ditolak: NIK yang sama, atau nama dan tanggal lahir yang sama, pada periode yang sama.
  Kakak-adik dengan nomor orang tua yang sama tetap diterima.
- Berkas disimpan di disk `local` (privat, tidak bisa diakses lewat URL), dicatat di `lampiran`. Berkas
  hanya JPG, PNG, atau PDF, maksimal 3 MB (`config/spmb.php`). Berkas yatim bisa tertinggal bila transaksi gagal
  setelah unggah; tidak berbahaya, bisa dibersihkan berkala.
- Perlindungan dasar: CSRF, pembatasan 10 permintaan per menit per alamat IP, dan kolom jebakan bot. Bila
  nanti ada spam, tambahkan captcha (misalnya Cloudflare Turnstile).
- Cek status menyembunyikan hasil seleksi (lulus/cadangan/tidak lulus) sampai
  `config('spmb.tampilkan_hasil_di_status')` diubah menjadi `true` pada hari pengumuman.
- Pernyataan persetujuan wajib dicentang tetapi belum disimpan sebagai catatan (belum ada kolomnya).

**Konversi**
- Syarat: `spmb_hasil.keputusan = lulus`, `spmb_daftar_ulang.status = selesai`, dan belum pernah dikonversi.
  Bila Anda ingin konversi saat status `lulus` saja, longgarkan pemeriksaan daftar ulang di `KonversiPendaftar`.
- NIS yayasan dibuat dari tabel `penomoran` dengan kode `nis_yayasan`, pola awal `{TAHUN}{SEQ:4}` (misalnya
  `20260001`), penghitung per tahun. **Sesuaikan polanya dengan format NIS yang sudah berlaku sebelum konversi
  pertama**: ubah baris `penomoran` untuk `nis_yayasan` (atau buat barisnya lebih dulu dengan pola Anda).
  Bila NIS lama sudah ada, nomor yang bentrok akan ditolak oleh unique index.
- Orang dengan NIK yang sudah ada dipakai ulang: wali kakak-adik tidak terduplikasi, dan anak yang naik jenjang
  (TK ke SD) tetap memakai satu baris `siswa` dengan NIS yang sama; `jenis_masuk` menjadi `naik_jenjang`.
  Keanggotaan lamanya harus sudah punya `tanggal_keluar`, bila tidak konversi ditolak dengan pesan yang jelas.
- Orang tua/wali tanpa NIK tidak bisa dikenali ulang dan akan menjadi baris `orang` baru; bersihkan duplikat
  secara berkala.
- Rombel diambil dari `spmb_daftar_ulang.rombel_id` atau opsi `--rombel`; boleh kosong dan ditempatkan nanti.
  Rombel harus milik sekolah dan tahun ajaran periode itu.
- Konversi massal: `php artisan spmb:konversi {id periode} [--dry-run] [--rombel=ID]`.
  Satu pendaftar: `--nomor=SPMB/FTN-01/2026/0001`. Yang gagal dilaporkan dengan alasannya dan tidak
  menghentikan yang lain.
- Ekspor daftar pendaftar ke CSV (tanpa NIK): `php artisan spmb:ekspor {id periode}`. Berkas berisi data
  pribadi: simpan dengan aman dan hapus setelah dipakai.

## Belum ada

- Layar admin: verifikasi berkas, input nilai seleksi dan hasil, daftar ulang, dan tombol konversi. Sampai ada,
  data ini diubah langsung di database atau lewat perintah artisan di atas.
- Akses unduh berkas pendaftar oleh petugas (berkas privat perlu rute yang memeriksa hak akses).
- Pengunggahan ulang berkas yang ditolak, notifikasi WhatsApp, dan akun login untuk orang tua dan siswa baru
  (peran `orang_tua` dan `siswa`).
- Batas usia per jenjang dan penutupan otomatis saat kuota penuh.
