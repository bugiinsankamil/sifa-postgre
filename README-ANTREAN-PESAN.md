# Antrean pesan (WhatsApp/email) dan notifikasi SPMB

Infrastruktur pesan keluar yang **tidak bergantung pada penyedia**. Selama penyedia belum dipilih, pesan dicatat di
log aplikasi saja (tidak ada pesan yang benar-benar terkirim). Setelah penyedia dipilih, cukup menambah satu kelas
pengirim; semua yang lain (antrean, templat, pengiriman ulang, jam tenang, pembatasan laju, tampilan admin) sudah siap.

**Status:** ditulis tanpa akses ke PHP dan PostgreSQL, belum pernah dijalankan. Jalankan tes sebelum dipakai.

## Berkas yang akan tertimpa

- `app/Services/Spmb/PendaftaranSpmb.php`: memicu peristiwa setelah pendaftaran tersimpan.
- `app/Services/Spmb/AlurSeleksiSpmb.php`: memicu peristiwa saat verifikasi, dan menambah `tetapkanBerkas()`.
- `app/Filament/Resources/Spmb/RelationManagers/BerkasRelationManager.php`: memakai `tetapkanBerkas()`.

## Pemasangan

1. Ekstrak ke akar proyek, lalu `php artisan migrate`.
2. `php artisan db:seed --class=TemplatPesanSeeder` (tidak menimpa templat yang sudah Anda ubah).
3. Atur zona waktu di `.env`: `APP_TIMEZONE=Asia/Jakarta` (jam tenang memakai zona waktu aplikasi).
   Pastikan juga `APP_URL` benar: tautan cek status di dalam pesan dibuat darinya.
4. Daftarkan pengiriman terjadwal di `routes/console.php`:
   ```php
   use Illuminate\Support\Facades\Schedule;

   Schedule::command('pesan:kirim')->everyMinute()->withoutOverlapping();
   ```
   dan pastikan cron server menjalankan scheduler Laravel:
   `* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1`
5. `php artisan test --filter="PengelolaPesanTest|KirimPesanTest|NotifikasiSpmbTest|PanelPesanTest"`
6. Uji jalurnya: `php artisan pesan:uji 08xxxxxxxxxx`, lalu `php artisan pesan:kirim`, lalu lihat `storage/logs/laravel.log`
   dan menu **Pesan > Antrean pesan** di panel admin.

## Pesan otomatis SPMB

Dikirim ke **kontak utama** (orang tua/wali yang dipilih saat mendaftar), WhatsApp:

| Peristiwa | Templat |
|---|---|
| Pendaftaran diterima | `spmb_terdaftar` (berisi nomor pendaftaran dan tautan cek status) |
| Panitia menolak berkas | `spmb_berkas_ditolak` (berisi nama berkas, alasan, tautan unggah ulang) |
| Pendaftar terverifikasi | `spmb_terverifikasi` |

Pengumuman hasil seleksi **sengaja tidak otomatis**: pesan hasil harus keluar pada hari pengumuman, bukan saat
panitia menetapkan hasil. Itu akan menjadi tindakan "Umumkan hasil" tersendiri.

Isi templat diubah di panel admin (**Pesan > Templat pesan**, untuk sysadmin, yayasan, direktur). Hanya variabel
yang tercantum yang boleh dipakai; variabel yang salah ditolak saat menyimpan.

## Cara kerja dan perlindungan

- **Antrean:** pesan ditulis ke tabel `antrean_pesan`, dikirim oleh perintah `pesan:kirim` tiap menit. Gagal
  dikirim akan dicoba lagi dengan jeda 1, 5, 15, 60, 180 menit (maksimal 5 kali), lalu ditandai Gagal; pesan yang
  Gagal bisa dikirim ulang dari panel. Kegagalan permanen (mis. nomor tidak terdaftar) langsung ditandai Gagal.
- **Tidak ada pesan ganda:** tiap pesan otomatis punya kunci unik per peristiwa. Berkas yang ditolak lalu diperbaiki
  lalu ditolak lagi menghasilkan pesan baru; penolakan yang sama tidak mengirim dua kali.
- **Jam tenang:** pesan yang jatuh pada 21:00 sampai 06:00 diundur ke pukul 06:00 (`config/pesan.php`).
- **Pembatasan laju:** maksimal 20 pesan per menit dengan jeda 1,5 detik antar pesan, supaya nomor WhatsApp tidak
  tampak seperti spam. Ubah di `config/pesan.php`.
- **Pesan tidak menggagalkan proses bisnis:** bila templat tidak ada atau pesan dimatikan (`PESAN_AKTIF=false`),
  pendaftaran dan verifikasi tetap berjalan normal.
- **Pesan macet** (proses mati di tengah pengiriman) diantrekan kembali otomatis setelah 10 menit.
- **Privasi:** di panel, nomor tujuan tersamar (hanya 4 digit terakhir) dan antrean dibatasi ke cabang pengguna.

## Menambah penyedia WhatsApp nanti

1. Buat kelas yang mengimplementasikan `App\Pesan\Pengirim` (satu method `kirim($tujuan, $isi, $judul)` yang
   mengembalikan `HasilKirim::sukses(...)` atau `HasilKirim::gagal(..., bisaDiulang: true|false)`).
2. Daftarkan di `config/pesan.php` pada `driver`, lalu arahkan `PESAN_PENGIRIM_WHATSAPP` ke namanya.
3. Selesai. Tes untuk pengirim baru cukup memeriksa pemetaan respons penyedia ke `HasilKirim`.

## Belum ada

- Pengirim WhatsApp sungguhan (menunggu keputusan penyedia).
- Pesan hasil seleksi, daftar ulang, dan status siswa baru; pengumuman massal.
- Preferensi berhenti berlangganan per nomor (penting bila nanti dipakai untuk pengumuman umum), dan status
  terkirim/dibaca dari penyedia (webhook).
- Pemberitahuan ke panitia saat berkas diunggah ulang.
