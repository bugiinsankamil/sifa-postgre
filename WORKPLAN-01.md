## Workplan Tahap Berikutnya: Pemantapan F2 → Keuangan Operasional → F3

**Rekomendasi saya: tuntaskan kesiapan operasional SDM terlebih dahulu, lalu lanjutkan Keuangan yang mendukung SPMB. Jangan langsung melompat ke Akademik.**

### Posisi proyek saat ini

- Commit terakhir: `3ab9275`; working tree bersih.
- Hasil pengujian terakhir pada sesi sebelumnya: **183 tes lulus, 494 asersi**.
- Database, model, dan layanan dasar absensi, cuti/izin, KPI, serta payroll tersedia.
- Panel absensi dan pengajuan cuti/izin tersedia; **panel KPI dan payroll belum dibuat**.
- Koneksi ke mesin absensi nyata, perintah pengolahan log terjadwal, dan integrasi payroll–akuntansi belum tersedia.

**Koreksi atas ringkasan sebelumnya:** istilah “SDM lengkap/selesai” terlalu luas. Pemeriksaan kode menunjukkan beberapa alur belum tersambung dari panel ke layanan bisnis dan masih ada celah konsistensi data. Tes yang lulus saat ini belum membuktikan kesiapan seluruh alur operasional.

---

## 1. Paket A — Perbaikan integritas dan akses SDM
**Prioritas: P0 — dikerjakan pertama**

### Pekerjaan
- Sambungkan form create/edit absensi, cuti, dan izin ke layanan bisnis. Saat ini halaman tersebut masih memakai penyimpanan bawaan Filament.
- Batasi pilihan pegawai/pengganti berdasarkan cabang yang berhak diakses.
- Pisahkan kewenangan:
  - melihat data;
  - mengelola master;
  - mengoreksi absensi;
  - menyetujui cuti/izin;
  - menilai KPI;
  - menghitung, menyetujui, dan membayar payroll.
- Terapkan pemeriksaan otorisasi pada aksi, bukan hanya menyembunyikan tombol.
- Lengkapi pembatasan cabang KPI, slip gaji, dan rincian turunannya.
- Kunci perubahan data yang sudah disetujui.
- Koreksi dokumentasi agar membedakan **fondasi tersedia**, **sudah teruji**, dan **siap operasional**.

### Kriteria selesai
- Form panel benar-benar menjalankan validasi layanan.
- Pengguna tidak dapat membaca atau mengubah data cabang lain.
- Peran tanpa hak persetujuan ditolak ketika menjalankan aksi.
- Tes Livewire mencakup create, edit, persetujuan, validasi gagal, dan akses lintas cabang.

---

## 2. Paket B — Absensi yang andal dan dapat diproses otomatis
**Prioritas: P0 | Dependensi: Paket A**

### Pekerjaan
- Perbaiki perhitungan keterlambatan manual yang masih berpotensi bernilai negatif.
- Hitung pulang cepat juga bagi pegawai yang datang terlambat.
- Tentukan hasil pengolahan log secara konsisten: masuk paling awal dan pulang paling akhir, termasuk log yang datang tidak berurutan.
- Tambahkan deduplikasi pada tingkat database, bukan hanya pemeriksaan sebelum insert.
- Amankan pengolahan bersamaan dengan transaksi dan penguncian yang tepat.
- Validasi kesesuaian cabang perangkat, penugasan pegawai, dan shift.
- Buat perintah pengolahan log terjadwal dengan batas batch, pencatatan galat, dan kemampuan mengulang proses.
- Tambahkan tampilan log belum terpetakan/gagal diproses.
- Sediakan rekap absensi bulanan dan ekspor CSV.

### Kriteria selesai
- Impor ulang tidak menggandakan data.
- Urutan kedatangan log tidak mengubah hasil akhir.
- Pemrosesan ulang aman dan tidak merusak status cuti/izin.
- Scheduler dan perintah pengolahan memiliki tes.

**Batas lingkup:** koneksi mesin nyata dilakukan setelah merek, model, protokol, dan contoh payload diketahui. Penyimpanan koordinat saat ini belum berarti fitur GPS/geofence sudah siap.

---

## 3. Paket C — Cuti/izin yang konsisten dan dapat diaudit
**Prioritas: P0 | Dependensi: Paket A–B**

### Pekerjaan
- Hitung jumlah hari berdasarkan aturan yang disepakati; jangan hanya mempercayai jumlah hari input.
- Validasi rentang tanggal, jenis cuti aktif, lampiran wajib, dan pengajuan bertumpang tindih.
- Tentukan perlakuan pengajuan lintas tahun.
- Periksa ulang saldo saat persetujuan.
- Kunci pengajuan dan saldo dalam transaksi untuk mencegah persetujuan/pembatalan ganda.
- Hubungkan absensi hasil persetujuan dengan sumber pengajuannya.
- Saat pembatalan, pulihkan atau hapus hanya absensi milik pengajuan tersebut.
- Pisahkan alasan pengajuan dari alasan penolakan.
- Lengkapi panel jenis cuti, saldo cuti, pembatalan, dan riwayat keputusan.

### Kriteria selesai
- Saldo tidak negatif atau terpotong dua kali.
- Pembatalan tidak menghapus absensi milik pengajuan lain.
- Persetujuan dan perubahan absensi berhasil atau batal sebagai satu kesatuan transaksi.
- Riwayat pelaku, waktu, dan alasan keputusan dapat ditelusuri.

**Keputusan diperlukan:** hari kerja vs hari kalender, hari libur, setengah hari, carry-over, serta siapa penyetuju dan apakah boleh menyetujui pengajuan sendiri. Alur langsung yang ada perlu dikonfirmasi sebelum dipertahankan atau dihubungkan ke engine approval generik.

---

## 4. Paket D — KPI operasional dan panel penilaian
**Prioritas: P1 | Dependensi: Paket A–B**

### Pekerjaan
- Buat panel periode, indikator, templat berbobot, penilaian, dan rincian.
- Validasi templat tidak kosong, bobot, target, serta kesesuaian pegawai/penilai.
- Terapkan status periode `draft → berjalan → ditutup`.
- Hapus skor lama ketika realisasi berubah; hitung ulang saat finalisasi.
- Larang perubahan penilaian yang telah disetujui.
- Perbaiki indikator kehadiran berdasarkan hari kerja yang berlaku, bukan seluruh hari kalender.
- Simpan snapshot aturan penilaian agar perubahan indikator tidak mengubah penilaian lama.
- Tambahkan rekap per pegawai, cabang, dan periode.

### Kriteria selesai
- Alur pembuatan sampai persetujuan berjalan melalui panel.
- Perubahan realisasi tidak meninggalkan skor kedaluwarsa.
- Periode ditutup dan penilaian disetujui tidak dapat diedit.

**Keputusan diperlukan:** komposisi indikator, definisi kehadiran, batas skor, predikat, dan perlakuan target nol. Ambang A/B/C/D saat ini perlu persetujuan produk.

---

## 5. Paket E — Payroll end-to-end
**Prioritas: P0 sebelum dipakai membayar | Dependensi: Paket A–C**

### Pekerjaan
- Ganti aritmetika uang berbasis `float` menjadi decimal/string dengan BCMath dan aturan pembulatan eksplisit.
- Pilih riwayat gaji berdasarkan tanggal efektif; penetapan gaji masa depan tidak boleh menghilangkan dasar perhitungan periode lama.
- Lengkapi komponen khusus pegawai, komponen nonaktif, dan validasi formula.
- Buat proses hitung satu periode secara utuh:
  `draft → dihitung → disetujui → dibayar → ditutup`.
- Hilangkan kebutuhan mengubah status periode secara manual seperti pada tes saat ini.
- Tetapkan aturan hitung ulang dan penguncian snapshot.
- Validasi pegawai termasuk dalam cabang/periode yang dibayar.
- Buat panel komponen, skema, riwayat gaji, periode, slip, dan rincian.
- Sediakan tampilan slip cetak serta ekspor CSV terotorisasi.

### Kriteria selesai
- Satu periode dapat dihitung, diperiksa, disetujui, dibayar, dan ditutup tanpa manipulasi database.
- Snapshot slip tetap konsisten setelah perubahan master.
- Proses ulang tidak menggandakan slip atau pembayaran.
- Angka diuji terhadap contoh payroll nyata yang sudah dianonimkan.

**Keputusan diperlukan:** cutoff, prorata, pembulatan, cuti tidak dibayar, PPh21, BPJS, serta otorisasi akses nominal gaji. PPh21/BPJS otomatis tidak boleh dianggap selesai sebelum aturan disepakati.

---

## 6. Paket F — Keuangan operasional dan integrasi SPMB/payroll
**Prioritas: P1 | Dependensi: Paket E untuk integrasi payroll**

Urutan pekerjaan:

1. Rekening kas/bank dan transaksi kas.
2. Tagihan pendaftaran serta daftar ulang SPMB.
3. Pembayaran dan alokasi ke tagihan.
4. Pemetaan akun dan jurnal otomatis payroll.
5. Event `PeriodeGajiDisetujui` beserta penanganan ulang yang tidak menggandakan jurnal.
6. Rekonsiliasi pembayaran payroll dan laporan dasar.

**Kriteria selesai:** sumber transaksi dapat ditelusuri sampai jurnal; pengulangan proses tidak membuat pencatatan ganda; periode akuntansi tertutup tetap terlindungi.

Bagan akun dan entitas penanggung biaya harus disepakati dengan bagian keuangan terlebih dahulu.

---

## 7. Paket G — UAT dan gerbang menuju F3
**Dependensi: paket operasional terkait selesai**

- Uji satu cabang dengan data anonim.
- Simulasikan satu bulan absensi sampai payroll.
- Cocokkan rekap, saldo cuti, slip, dan jurnal dengan perhitungan manual.
- Jalankan tes terarah, formatter, lalu seluruh `php artisan test` pada PostgreSQL.
- Verifikasi migrasi upgrade pada salinan database; **tanpa `migrate:fresh` di development**.
- Perbarui handover, panduan operator, backup/restore, serta daftar keterbatasan.

Setelah lolos, lanjutkan **F3 Akademik dasar**: kurikulum/mapel → penugasan mengajar → jadwal → presensi/jurnal mengajar → penilaian/rapor. LMS/CBT tetap setelah fondasi tersebut stabil.

### Urutan eksekusi yang disarankan

**A → B → C → E → F**, dengan **D** dikerjakan setelah aturan KPI disepakati, lalu **G** sebagai gerbang rilis.

**Paket pertama yang paling tepat adalah Paket A**, bukan menambah modul baru. Rencana ini belum mengubah berkas atau menjalankan ulang tes karena masih dalam mode Plan. Jika urutannya disetujui, **toggle to Act mode** untuk mulai Paket A; keputusan peran/lingkup yang belum tercantum akan dikonfirmasi sebelum implementasi.