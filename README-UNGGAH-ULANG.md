# Unggah ulang berkas yang ditolak (SPMB)

Tambahan di atas paket admin SPMB. Ekstrak ke akar proyek; berkas berikut tertimpa:
`app/Http/Controllers/Spmb/PendaftaranSpmbController.php`, `routes/spmb.php`,
`resources/views/spmb/status.blade.php`, `app/Filament/Widgets/RingkasanSpmb.php`, dan
`app/Filament/Resources/Spmb/RelationManagers/BerkasRelationManager.php`.

Tidak ada migrasi baru. Jalankan `php artisan test`.

## Cara kerja

1. Panitia menandai berkas **Tidak valid** beserta alasannya (di panel admin, tab Berkas persyaratan).
2. Orang tua membuka `/spmb/status`, memasukkan nomor pendaftaran dan tanggal lahir, lalu melihat alasan penolakan
   dan formulir **Unggah perbaikan** di bawah berkas yang ditolak.
3. Berkas baru menggantikan yang lama, statusnya kembali **Menunggu verifikasi**, dan panitia melihatnya di dashboard
   ("n berkas perlu dicek") dan di kolom Diperbarui pada tab Berkas.

## Aturan dan keamanan

- Hanya berkas berstatus tidak valid yang bisa diganti, dan hanya selama pendaftar berstatus Draft atau Terdaftar.
  Setelah panitia menandai pendaftar Terverifikasi, unggah ulang ditutup.
- Pendaftar dikenali lewat sesi selama 30 menit sejak berhasil cek status (nomor pendaftaran + tanggal lahir),
  dan hanya bisa mengganti berkas miliknya sendiri. Dibatasi 10 permintaan per menit; format JPG, PNG, PDF;
  ukuran maksimal sesuai `config/spmb.php`.
- Berkas lama tidak dihapus: catatannya di-soft-delete dan filenya tetap ada di disk sebagai arsip.
  Bersihkan berkala bila ruang disk menjadi masalah.

## Catatan

- Setelah berkas diganti, panitia tidak mendapat pemberitahuan otomatis selain angka di dashboard.
  Pemberitahuan WhatsApp (baik ke panitia maupun orang tua) menunggu pemilihan penyedia WhatsApp.
