## Workplan Tahap Berikutnya: Penutupan Celah SDM → Cuti/Izin → Payroll → Keuangan → F3

> Posisi saat ini: Paket A–B memiliki implementasi awal, tetapi kriteria selesai di WORKPLAN-01.md belum seluruhnya terpenuhi. Paket C sedang berlangsung dengan perubahan layanan cuti/izin yang belum di-commit. Paket D–F masih dalam fondasi. Jangan melompat ke Akademik (F3) sebelum gerbang UAT dilalui.

---

## Posisi proyek saat ini

- **Commit terakhir:** `ccf6487` — `feat: Paket B - absensi andal dan otomatis`.
- **Working tree:** belum bersih. Perubahan yang sedang dikerjakan:
  - `app/Services/Sdm/AbsensiService.php`
  - `app/Services/Sdm/CutiService.php`
  - `app/Services/Sdm/IzinService.php`
  - `app/Models/JenisCuti.php`
  - `database/migrations/2026_11_17_050220_perbaiki_sdm_cuti_izin.php` (belum terlacak).
- **Status uji terakhir (HANDOVER.md):** 189 tes lulus setelah Paket A; Paket B belum dikonfirmasi pengguna. Belum ada bukti simulasi satu bulan operasional.
- **Panel SDM yang tersedia:** Pegawai, Jabatan, Shift Kerja, Pelatihan, Absensi, Pengajuan Cuti, Pengajuan Izin, Lokasi Absensi, dll.
- **Panel yang belum dibuat:** KPI, Payroll, Jenis Cuti, Saldo Cuti, Periode Gaji, Slip Gaji, Komponen Gaji, Skema Gaji, Riwayat Gaji.
- **Migrasi dasar** untuk absensi, log absensi, cuti, izin, KPI, dan payroll sudah ada.
- **Fondasi akuntansi** (entitas, akun, jurnal, periode, saldo) sudah tersedia; kas/bank, tagihan SPMB, dan integrasi payroll belum dikerjakan.

---

## Catatan penting tentang kesiapan saat ini

- **Paket A belum sepenuhnya memenuhi kriteria selesai.** Tes `test_admin_tidak_bisa_setujui_cuti()` hanya memeriksa `canApprovePengajuan()`, bukan menjalankan aksi persetujuan tanpa hak. Pembatasan cabang untuk KPI, slip gaji, dan rincian turunannya belum dipastikan tersedia.
- **Paket B sudah memiliki komitmen kode** (perhitungan absensi, pulang cepat, urutan log, deduplikasi database, command, scheduler). Namun, command masih belum diuji lewat Artisan; batas batch default masih `null`; pelaporan log gagal/belum terpetakan dan rekap/ekspor CSV belum selesai.
- **Paket C sedang dikerjakan.** Perubahan yang belum di-commit menambahkan `pengajuan_cuti_id`/`pengajuan_izin_id`, kolom `alasan_penolakan`, serta penguncian transaksi di `CutiService`. Perhitungan hari masih bergantung pada input pengguna; aturan hari kerja/kalender, libur, lintas tahun, carry-over, dan otorisasi penyetuju masih perlu disepakati.
- **Paket E masih berbasis `float`.** Pemilihan gaji pokok masih mengandalkan status aktif, bukan tanggal efektif pada periode tertentu. Alur status periode lengkap (`draft → dihitung → disetujui → dibayar → ditutup`) belum diterapkan.
- **Paket F masih tertahan** karena integrasi payroll belum stabil dan bagan akun/entitas penanggung biaya belum disepakati.
- **Paket G belum dimulai.** Belum ada simulasi satu bulan operasional pada satu cabang.

---

## 1. Paket A/B — Penutupan celah integritas, akses, dan absensi andal
**Prioritas: P0 | Status: implementasi tersedia, kriteria belum sepenuhnya terpenuhi**

### Pekerjaan
1. Audit otorisasi pada **eksekusi aksi**, bukan hanya visibilitas tombol:
   - membuat/mengedit data;
   - mengoreksi absensi;
   - menyetujui/menolak cuti/izin;
   - menilai/menyetujui KPI;
   - menghitung/menyetujui/memproses payroll.
2. Pastikan form create/edit absensi, cuti, dan izin benar-benar memanggil layanan bisnis dan menangkap exception domain.
3. Batasi pilihan pegawai/pengganti hanya dari cabang yang berhak diakses pengguna.
4. Terapkan pembatasan cabang pada KPI, slip gaji, rincian, log absensi, dan rekap.
5. Kunci perubahan data yang sudah disetujui/dibayar/ditutup.
6. Perbaiki command `absensi:proses-log` agar memiliki batas batch default yang aman.
7. Tambahkan penanganan log gagal/belum terpetakan dan tampilannya di panel.
8. Sediakan rekap absensi bulanan serta ekspor CSV terotorisasi.
9. Uji command lewat Artisan, scheduler, proses bersamaan, dan idempotensi ulang.

### Kriteria selesai
- Pengguna tanpa hak ditolak ketika memaksa eksekusi aksi.
- Pengguna tidak dapat membaca atau mengubah data cabang lain.
- Impor ulang tidak menggandakan data meskipun log datang tidak berurutan.
- Scheduler dan command memiliki batas batch dan catatan galat.
- Tes mencakup create, edit, persetujuan, validasi gagal, akses lintas cabang, dan proses log via Artisan.

---

## 2. Paket C — Cuti/izin yang konsisten dan dapat diaudit
**Prioritas: P0 | Dependensi: Paket A–B | Status: sedang dikerjakan, perubahan belum di-commit**

### Pekerjaan
1. Selesaikan dan commit perubahan yang sedang dikerjakan:
   - kolom `alasan_penolakan` terpisah dari `alasan`;
   - `pengajuan_cuti_id` dan `pengajuan_izin_id` pada `absensi_pegawai`;
   - penguncian transaksi di persetujuan, penolakan, dan pembatalan.
2. Hitung jumlah hari berdasarkan aturan yang disepakati, bukan input pengguna.
3. Validasi rentang tanggal, jenis cuti aktif, lampiran wajib, dan pengajuan bertumpang tindih.
4. Tentukan perlakuan lintas tahun, setengah hari, dan hari libur.
5. Periksa ulang saldo saat persetujuan dalam transaksi terkunci.
6. Hubungkan absensi hasil persetujuan dengan sumber pengajuannya.
7. Saat pembatalan, pulihkan saldo dan hapus hanya absensi milik pengajuan tersebut.
8. Lengkapi panel jenis cuti, saldo cuti, pembatalan, dan riwayat keputusan.
9. Pastikan otorisasi cabang dan peran diterapkan di seluruh aksi.

### Kriteria selesai
- Saldo tidak negatif atau terpotong dua kali.
- Pembatalan tidak menghapus absensi milik pengajuan lain.
- Persetujuan dan perubahan absensi berhasil atau batal sebagai satu kesatuan transaksi.
- Riwayat pelaku, waktu, dan alasan keputusan dapat ditelusuri.

**Keputusan diperlukan:** hari kerja vs hari kalender, hari libur, setengah hari, carry-over, siapa penyetuju, dan apakah boleh menyetujui pengajuan sendiri.

---

## 3. Paket D — KPI operasional dan panel penilaian
**Prioritas: P1 | Dependensi: Paket A–B | Status: fondasi layanan tersedia, panel belum dibuat**

### Pekerjaan
1. Buat panel periode, indikator, templat berbobot, penilaian, dan rincian.
2. Validasi templat tidak kosong, total bobot, target, serta kesesuaian pegawai/penilai.
3. Terapkan siklus periode `draft → berjalan → ditutup`.
4. Simpan snapshot aturan penilaian agar perubahan indikator tidak mengubah penilaian lama.
5. Hapus skor lama ketika realisasi berubah; hitung ulang saat finalisasi.
6. Larang perubahan penilaian yang telah disetujui.
7. Perbaiki indikator kehadiran berdasarkan hari kerja yang berlaku.
8. Tambahkan rekap per pegawai, cabang, dan periode.

### Kriteria selesai
- Alur pembuatan sampai persetujuan berjalan melalui panel.
- Perubahan realisasi tidak meninggalkan skor kedaluwarsa.
- Periode ditutup dan penilaian disetujui tidak dapat diedit.

**Keputusan diperlukan:** komposisi indikator, definisi kehadiran, batas skor, predikat, dan perlakuan target nol.

---

## 4. Paket E — Payroll end-to-end
**Prioritas: P0 sebelum dipakai membayar | Dependensi: Paket A–C | Status: fondasi layanan tersedia, belum siap pembayaran**

### Pekerjaan
1. Sepakati cutoff, prorata, pembulatan, cuti tidak dibayar, PPh21, BPJS, dan akses nominal.
2. Ganti aritmetika uang berbasis `float` menjadi decimal/string dengan BCMath dan aturan pembulatan eksplisit.
3. Pilih riwayat gaji berdasarkan tanggal efektif pada periode yang dibayar.
4. Lengkapi komponen khusus pegawai, komponen nonaktif, dan validasi formula.
5. Implementasikan alur periode utuh: `draft → dihitung → disetujui → dibayar → ditutup`.
6. Hilangkan kebutuhan mengubah status periode secara manual seperti pada tes saat ini.
7. Tetapkan aturan hitung ulang dan penguncian snapshot slip.
8. Validasi pegawai termasuk dalam cabang/periode yang dibayar.
9. Buat panel komponen, skema, riwayat gaji, periode, slip, dan rincian.
10. Sediakan tampilan slip cetak serta ekspor CSV terotorisasi.
11. Cocokkan angka dengan contoh payroll nyata yang sudah dianonimkan.

### Kriteria selesai
- Satu periode dapat dihitung, diperiksa, disetujui, dibayar, dan ditutup tanpa manipulasi database.
- Snapshot slip tetap konsisten setelah perubahan master.
- Proses ulang tidak menggandakan slip atau pembayaran.
- Angka diuji terhadap contoh payroll nyata yang sudah dianonimkan.

**PPh21/BPJS otomatis tidak boleh dianggap selesai sebelum aturan disepakati.**

---

## 5. Paket F — Keuangan operasional dan integrasi SPMB/payroll
**Prioritas: P1 | Dependensi: Paket E untuk integrasi payroll**

### Urutan pekerjaan
1. Rekening kas/bank dan transaksi kas.
2. Tagihan pendaftaran serta daftar ulang SPMB.
3. Pembayaran dan alokasi ke tagihan.
4. Pemetaan akun dan jurnal otomatis payroll.
5. Event `PeriodeGajiDisetujui` beserta penanganan ulang yang tidak menggandakan jurnal.
6. Rekonsiliasi pembayaran payroll dan laporan dasar.

### Kriteria selesai
- Sumber transaksi dapat ditelusuri sampai jurnal.
- Pengulangan proses tidak membuat pencatatan ganda.
- Periode akuntansi tertutup tetap terlindungi.

**Bagan akun dan entitas penanggung biaya harus disepakati dengan bagian keuangan terlebih dahulu.**

---

## 6. Paket G — UAT dan gerbang menuju F3
**Dependensi: paket operasional terkait selesai**

### Pekerjaan
1. Pilih satu cabang dan jalankan simulasi satu bulan operasional.
2. Masukkan data anonim: absensi harian, cuti/izin, KPI, dan payroll.
3. Cocokkan rekap absensi, saldo cuti, slip gaji, pembayaran, dan jurnal dengan perhitungan manual.
4. Jalankan tes terarah, formatter, lalu seluruh `php artisan test` pada PostgreSQL.
5. Verifikasi migrasi upgrade pada salinan database; **tanpa `migrate:fresh` di development**.
6. Perbarui handover, panduan operator, backup/restore, serta daftar keterbatasan.

### Kriteria selesai
- Satu bulan simulasi tercatat konsisten dari absensi sampai jurnal.
- Semua tes lolos di PostgreSQL.
- Upgrade database dari keadaan sekarang berhasil di salinan tanpa kehilangan data.

Setelah lolos, lanjutkan **F3 Akademik dasar**: kurikulum/mapel → penugasan mengajar → jadwal → presensi/jurnal mengajar → penilaian/rapor. LMS/CBT tetap setelah fondasi tersebut stabil.

---

## Urutan eksekusi yang disarankan

1. **Selesaikan dan commit perubahan Paket C yang sedang dikerjakan** setelah memastikan tidak merusak tes Paket A–B.
2. **Tutup celah Paket A/B** (audit otorisasi, lintas cabang, batas batch, rekap/CSV, uji command).
3. **Lanjutkan Paket C** sampai kriteria selesai dan semua tes baru lolus.
4. **Paket E** (payroll end-to-end) setelah cuti/izin stabil.
5. **Paket F** (keuangan operasional) setelah payroll stabil.
6. **Paket D** (KPI) setelah aturan KPI disepakati; dapat berjalan paralel dengan E/F setelah C selesai.
7. **Paket G** sebagai gerbang rilis sebelum masuk F3.

**Paket pertama yang paling tepat saat ini adalah penutupan celah Paket A–B dan penyelesaian perubahan Paket C yang sedang berlangsung.**

---

## Batasan dan asumsi

- Koneksi mesin absensi nyata tetap ditunda sampai merek, model, protokol, dan contoh payload diketahui.
- GPS/geofence belum menjadi fitur siap pakai meskipun kolom koordinat tersedia.
- PPh21/BPJS otomatis tidak dibangun sebelum aturan dan contoh perhitungan disepakati.
- Semua perubahan status/transaksi dilakukan melalui service; dispatch event setelah transaksi selesai.
- Tes selalu di PostgreSQL, bukan SQLite.
