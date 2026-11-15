# Pengumuman hasil SPMB per periode

Fitur ini menggantikan pengumuman serentak melalui perubahan konfigurasi global. Hasil tetap
tersembunyi pada halaman cek status sampai periode yang bersangkutan diumumkan. Konfigurasi
lama `spmb.tampilkan_hasil_di_status` tetap dipertahankan sebagai kompatibilitas.

## Cara memasang

```bash
php artisan migrate
php artisan db:seed --class=TemplatPesanSeeder
php artisan test
```

`TemplatPesanSeeder` kini juga dipanggil oleh `DatabaseSeeder`. Seeder memakai `insertOrIgnore`,
sehingga isi templat yang sudah disesuaikan di panel tidak ditimpa.

## Cara menggunakan

1. Pastikan hasil pendaftar sudah ditetapkan melalui alur seleksi. Menetapkan hasil tidak membuat pesan.
2. Buka **SPMB > Periode SPMB**, lalu pilih **Umumkan hasil** pada periode yang dikehendaki.
3. Periksa ringkasan jumlah pendaftar lulus, cadangan, dan tidak lulus pada dialog konfirmasi.
4. Setelah disetujui, hasil tampil di halaman cek status dan pesan WhatsApp dimasukkan ke antrean.
   Pengiriman tetap dilakukan oleh jadwal `pesan:kirim`.

Aksi tersedia untuk `sysadmin`, `yayasan`, `direktur`, dan `kepala_cabang`. Akses cabang tetap
dibatasi oleh `CabangKonteks`. Pendaftar dengan hasil dan status `lulus`, `cadangan`, `tidak_lulus`,
atau `daftar_ulang` disertakan. Pendaftar tanpa hasil atau yang sudah mundur tidak disertakan.

**Pengumuman tidak dapat dibatalkan atau diulang.** Periode dikunci selama transaksi untuk mencegah
dua proses bersamaan. Kunci `spmb_hasil:{pendaftar_id}:{keputusan}` mencegah pesan ganda. Pendaftar
tanpa kontak utama/nomor dan templat nonaktif dilewati tanpa menggagalkan pengumuman; jumlah pada
notifikasi panel adalah jumlah pendaftar yang memperoleh pesan antrean.

## Verifikasi

Tes `tests/Feature/PengumumanHasilSpmbTest.php` mencakup kerahasiaan hasil, status publik, semua
keputusan, pendaftar yang sedang daftar ulang, isolasi periode/cabang, penolakan pengulangan dan
periode tanpa hasil, ringkasan konfirmasi, serta templat nonaktif. Jalankan pada database PostgreSQL
khusus tes (`sifa_test`), bukan database development.
