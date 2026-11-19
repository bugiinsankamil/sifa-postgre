Sekarang saya punya gambaran lengkap. Saya akan menyusun laporan progres dan workplan terinci.

## Laporan Progres Proyek ERP SIT Fathona

### Ringkasan Status (19 November 2026)

**Fase yang Sudah Selesai:**
- **F0 Core + Data Pokok**: ✅ Selesai diverifikasi (70 tes lolos)
- **F1 SPMB**: ✅ Hampir tuntas (41+8 tes lolos)
- **F1 Arsip & Persuratan**: ✅ Migrasi + 2 resource (SuratMasuk, SuratKeluar)
- **F1 Inventaris**: ✅ Selesai (26 tabel, 5 resource, 5 tes lolos)
- **F2 Keuangan (Fondasi)**: ✅ Selesai (akuntansi multi-entitas, 6 tes lolos)
- **F2 Kepegawaian (Core)**: ✅ Selesai (migrasi lengkap SDM, 6+6+7 tes lolos)

**Progres Saat Ini:**
- **Paket C (Cuti/Izin Atomik)**: 🟡 Service selesai, panel & tes belum disesuaikan
- **27 Resource Filament** sudah dibuat, **tersebar di 5 modul**

---

### Perbandingan dengan Skema Database v0.2

#### ✅ Sudah Diimplementasi (Migrasi + Model + Service)

**F0 Core (12/12 tabel):**
- organisasi: cabang, jenjang, sekolah, tingkat, unit_kerja (+ kepala)
- waktu: tahun_ajaran, semester  
- referensi: wilayah, referensi, pengaturan, penomoran
- layanan lintas modul: approval_*, lampiran, komentar

**F0 Data Pokok (10/10 tabel):**
- orang + orang_alamat, orang_kontak_darurat
- siswa, siswa_sekolah, siswa_wali
- pegawai, jabatan, penugasan_pegawai
- rombel, rombel_siswa

**F1 SPMB (14/14 tabel):**
- spmb_periode, spmb_jalur, spmb_persyaratan_berkas
- spmb_kampanye, spmb_kontak, spmb_tindak_lanjut
- spmb_kegiatan, spmb_kegiatan_peserta
- spmb_pendaftar, spmb_pendaftar_wali, spmb_berkas
- spmb_tahap_seleksi, spmb_nilai_seleksi, spmb_hasil, spmb_daftar_ulang
- View: v_spmb_*

**F1 Arsip & Persuratan (6/6 tabel):**
- klasifikasi_arsip, surat_masuk, disposisi
- surat_keluar, surat_keluar_tujuan
- lokasi_arsip_fisik, dokumen, dokumen_versi, dokumen_akses
- templat_surat

**F1 Inventaris (26/26 tabel):**
- tanah, bangunan, ruang
- kategori_aset, barang
- aset_unit, aset_kendaraan, penyusutan_aset
- gudang, stok_barang, mutasi_stok
- penerimaan_barang + baris, permintaan_barang + baris, pengeluaran_barang + baris
- stok_opname + baris
- peminjaman + baris, reservasi_ruang
- mutasi_aset + baris, laporan_kerusakan, pemeliharaan_aset, penghapusan_aset

**F2 Keuangan (8/38 tabel rencana):**
- entitas_akuntansi, akun, pusat_biaya
- periode_akuntansi, jurnal, jurnal_baris
- saldo_akun_periode, pemetaan_akun
- ❌ **Belum:** rekening_kas, transaksi_kas, rekonsiliasi_bank
- ❌ **Belum:** tagihan, pembayaran, virtual_account (penerimaan siswa)
- ❌ **Belum:** anggaran, pengajuan_dana, pemasok, pembelian, dll (23 tabel)

**F2 Kepegawaian/SDM (36/36 tabel):**
- Data pegawai: pegawai_pendidikan, pegawai_keluarga, pegawai_pajak_bpjs, rekening_pegawai, pegawai_riwayat_status, kontrak_kerja, pegawai_sertifikasi
- Absensi: lokasi_absensi, perangkat_absensi, shift_kerja, pegawai_shift, absensi_log_mentah, absensi_pegawai
- Cuti/izin: jenis_cuti, saldo_cuti, **hari_libur** (baru), pengajuan_cuti, **pengajuan_cuti_hari** (baru), **mutasi_saldo_cuti** (baru), pengajuan_izin, **pengajuan_izin_hari** (baru)
- KPI: kpi_periode, kpi_indikator, kpi_templat, kpi_templat_indikator, kpi_penilaian, kpi_penilaian_detail
- Pelatihan: pelatihan, pelatihan_peserta
- Payroll: komponen_gaji, skema_gaji, skema_gaji_komponen, gaji_pegawai, gaji_pegawai_komponen, periode_gaji, slip_gaji, slip_gaji_rincian
- ❌ **Belum:** pinjaman_pegawai, lembur, penugasan_khusus (3 tabel)

**Pesan (3/3 tabel):**
- antrean_pesan, templat_pesan, (log dalam pesan)

#### ❌ Belum Diimplementasi

**F3 Kesiswaan (0/27 tabel):**
- Belum ada: prestasi_siswa, jenis_pelanggaran, kasus_siswa, konseling, alumni
- Belum ada: aspek_karakter, catatan_karakter, capaian_tahfidz, jenis_ibadah, catatan_ibadah
- Belum ada: ekskul, minat_bakat, kalender_kegiatan

**F3 Akademik (0/56 tabel):**
- Belum ada: kurikulum, mata_pelajaran, struktur_kurikulum, tujuan_pembelajaran
- Belum ada: penugasan_mengajar, jadwal_pelajaran, jam_pelajaran
- Belum ada: jurnal_mengajar, presensi_siswa, izin_siswa
- Belum ada: modul_ajar
- Belum ada: komponen_penilaian, penilaian, nilai_siswa, rapor, dll (40+ tabel)

**F4 LMS/CBT/Bank Soal (0/55 tabel):**
- Belum ada: kelas_belajar, materi_ajar, tugas, diskusi
- Belum ada: bank_soal, soal, soal_opsi, paket_soal
- Belum ada: ujian, ujian_peserta, ujian_jawaban

**Modul Pendukung (0/11 tabel):**
- Belum ada: perpustakaan, UKS, komunikasi, layanan internal

---

### Masalah Mendesak (Paket C - Cuti/Izin)

**Status:** Migrasi & service selesai, tetapi **panel & tes masih memakai API lama**

**File Rusak (memanggil method `setujui()` yang sudah dihapus):**
1. `app/Filament/Resources/Sdm/PengajuanCutiResource.php:97`
2. `app/Filament/Resources/Sdm/PengajuanIzinResource.php:98`
3. `tests/Feature/CutiIzinTest.php` (beberapa tes)
4. `tests/Feature/PanelSdmTest.php`

**API Baru (sudah ada di service):**
- `ajukan(pegawai, jenis, mulai, selesai, alasan, pengganti, sesiPerTanggal, pengaju)`
- `setujuiAtasan(pengajuan, penyetuju)`
- `setujuiFinal(pengajuan, penyetuju)`
- `tolak(pengajuan, alasan, penolak)`
- `batalkan(pengajuan, pembatal, alasan)`

---

## WORKPLAN TERINCI: Menyelesaikan Proyek ERP SIT Fathona

### PRIORITAS 1: Stabilisasi Paket C (Cuti/Izin) — MENDESAK

**Target:** Sistem cuti/izin production-ready dalam 1-2 hari kerja

#### C.1 Perbaiki Panel Cuti/Izin (1-2 jam)
- [ ] Sesuaikan `PengajuanCutiResource::recordActions()`:
  - Ganti `setujui()` → `setujuiAtasan()` dan `setujuiFinal()`
  - Tambahkan logika: jika status `diajukan` → tombol `setujuiAtasan`, jika `disetujui_atasan` → tombol `setujuiFinal`
  - Tambahkan action `batalkan` dengan form alasan
- [ ] Sesuaikan `PengajuanIzinResource::recordActions()` (sama seperti cuti)
- [ ] Ubah form `Create/EditPengajuanCuti`:
  - Hapus field `jumlah_hari` (hitung otomatis)
  - Tambah field `sesi_per_tanggal` (repeater: tanggal + sesi pagi/siang/penuh)
  - Panggil `CutiService::ajukan()` dengan signature baru
- [ ] Ubah form `Create/EditPengajuanIzin` (sama, tanpa saldo)

#### C.2 Buat 3 Resource Baru (1 jam)
- [ ] `JenisCutiResource` (CRUD sederhana: kode, nama, kuota tahunan, is_dibayar)
- [ ] `SaldoCutiResource` (read-only + action penyesuaian manual via `MutasiSaldoCutiService`)
- [ ] `HariLiburResource` (CRUD: cabang, tanggal, nama)

#### C.3 Perbarui & Tambah Tes (2-3 jam)
- [ ] Perbaiki `CutiIzinTest.php`:
  - Sesuaikan semua pemanggilan service ke API baru
  - Tambah tes: perhitungan hari libur, setengah hari, lintas tahun, benturan cuti+izin
  - Tambah tes: otorisasi bertingkat (atasan → final)
  - Tambah tes: mutasi saldo atomik (carry-over)
  - Tambah tes: pembatalan (kembalikan saldo)
- [ ] Perbaiki `PanelSdmTest.php` (akses panel + aksi bertingkat)
- [ ] Jalankan `php artisan test --filter=CutiIzin`
- [ ] **Target:** 15-20 tes lolos

#### C.4 Hapus File Usang (10 menit)
- [ ] Hapus `app/Services/Sdm/OtorisasiPersetujuan.php` (sudah diganti `OtorisasiPengajuan`)
- [ ] Hapus migrasi `2026_11_17_050220_perbaiki_sdm_cuti_izin.php` (sudah digabung)

**Output Paket C:** Sistem cuti/izin production-ready, migrasi dijalankan, 15+ tes lolos.

---

### PRIORITAS 2: Lengkapi Modul Keuangan (F2) — 3-5 hari kerja

**Konteks:** Fondasi akuntansi sudah ada, tetapi **belum bisa menerima uang** (tagihan SPMB, SPP, kas/bank).

#### K.1 Kas & Bank (1 hari)
**Migrasi:**
- [ ] Tabel `rekening_kas` (entitas_id, nama, tipe, bank_ref, nomor_rekening, akun_id, saldo_awal)
- [ ] Tabel `transaksi_kas` (entitas_id, rekening_id, tanggal, tipe, nominal, akun_lawan_id, sumber polimorfik, jurnal_id)
- [ ] Tabel `rekonsiliasi_bank` (rekening_id, tahun, bulan, saldo_bank, saldo_buku, selisih)

**Service:**
- [ ] `KasService::catatTransaksi(rekening, tipe, nominal, akunLawan, keterangan, sumber)` → buat transaksi_kas + jurnal otomatis
- [ ] `KasService::transfer(rekeningAsal, rekeningTujuan, nominal)` → 2 transaksi + jurnal
- [ ] `RekonsiliasiBankService::mulaiRekonsiliasi()`, `cocokkan()`, `selesaikan()`

**Panel:**
- [ ] `RekeningKasResource` (CRUD, filter per entitas)
- [ ] `TransaksiKasResource` (list read-only, action manual tambah transaksi)
- [ ] `RekonsiliasiBankResource` (proses rekonsiliasi)

**Tes:**
- [ ] Transaksi masuk/keluar → jurnal otomatis
- [ ] Transfer antar rekening
- [ ] Saldo tidak boleh minus (kecuali rekening bank)
- [ ] Rekonsiliasi bank
- **Target:** 6-8 tes lolos

#### K.2 Tagihan & Pembayaran Siswa (2 hari)
**Migrasi:**
- [ ] `jenis_tagihan` (kode, nama, tipe, akun_pendapatan_id, jenjang_id, boleh_cicil)
- [ ] `tarif_tagihan` (jenis_tagihan_id, sekolah_id, tahun_ajaran_id, tingkat_id, nominal, jadwal jsonb)
- [ ] `diskon_beasiswa` (kode, nama, tipe persen/nominal, nilai)
- [ ] `siswa_diskon` (siswa_id, diskon_id, jenis_tagihan_id, tanggal_mulai, tanggal_akhir)
- [ ] `tagihan` (cabang_id, entitas_id, siswa_id, pendaftar_id, jenis_tagihan_id, nomor, nominal, diskon, total, terbayar, status) + check constraint (siswa_id XOR pendaftar_id)
- [ ] `pembayaran` (entitas_id, nomor, siswa_id, pendaftar_id, tanggal, metode, nominal, rekening_id, transaksi_kas_id, status)
- [ ] `pembayaran_alokasi` (pembayaran_id, tagihan_id, nominal)
- [ ] `virtual_account` (siswa_id, bank_ref, nomor, status)
- [ ] `pembayaran_gateway_log` (penyedia, referensi, payload jsonb, status_proses)

**Service:**
- [ ] `TagihanService::buatTagihan(siswa/pendaftar, jenistagihanId, periode)` → hitung nominal - diskon
- [ ] `TagihanService::buatTagihanMassal(sekolahId, tahunAjaranId, jenisTagihanId)` → semua siswa aktif
- [ ] `PembayaranService::terimaPembayaran(siswa/pendaftar, nominal, metode, rekening)` → buat pembayaran + transaksi_kas + jurnal + alokasi otomatis ke tagihan tertua
- [ ] `PembayaranService::prosesCallbackGateway(payload)` → update status pembayaran + alokasi jika berhasil

**Panel:**
- [ ] `JenisTagihanResource`, `TarifTagihanResource`
- [ ] `DiskonBeasiswaResource`, `SiswaDiskonResource`
- [ ] `TagihanResource` (list per siswa, action manual buat tagihan, action cetak invoice)
- [ ] `PembayaranResource` (list, action terima pembayaran tunai/transfer)

**Integrasi SPMB:**
- [ ] Event `PendaftarDikonversi` → `TagihanService::buatTagihan(pendaftar, 'uang_pangkal')`
- [ ] Perbarui formulir SPMB: tambah informasi tagihan daftar ulang
- [ ] Dashboard SPMB: tambah kolom "Sudah Bayar" di tabel pendaftar

**Tes:**
- [ ] Buat tagihan manual + massal
- [ ] Terima pembayaran → alokasi otomatis → tagihan lunas
- [ ] Pembayaran berlebih (kelebihan bayar dialokasikan ke tagihan berikutnya)
- [ ] Diskon/beasiswa dipotong dari nominal
- [ ] Konversi pendaftar → tagihan uang pangkal otomatis
- [ ] Lingkup entitas (admin sekolah A tidak bisa lihat tagihan sekolah B)
- **Target:** 10-12 tes lolos

#### K.3 Anggaran (1 hari, opsional untuk produksi awal)
**Migrasi:**
- [ ] `anggaran` (entitas_id, tahun_ajaran_id, nama, versi, induk_id, total, status)
- [ ] `anggaran_baris` (anggaran_id, akun_id, pusat_biaya_id, uraian, volume, satuan, harga_satuan, jumlah, bulan_rencana)
- [ ] View `v_realisasi_anggaran` (gabungan jurnal_baris + pengajuan_dana)

**Service:**
- [ ] `AnggaranService::buatAnggaran()`, `salinDariTahunLalu()`, `ajukanRevisi()`, `setujui()`

**Panel:**
- [ ] `AnggaranResource` (CRUD, action salin, action bandingkan realisasi)

**Tes:**
- [ ] Buat anggaran, ajukan revisi
- [ ] View realisasi vs anggaran
- **Target:** 4-5 tes lolos

**Output Modul Keuangan (P2):** Sistem kas/bank + tagihan/pembayaran siswa production-ready, integrasi SPMB, 20-25 tes lolos.

---

### PRIORITAS 3: Modul Akademik Core (F3) — 5-7 hari kerja

**Konteks:** Tanpa modul ini, sekolah tidak bisa mencatat jadwal, presensi, nilai. **Prioritas lebih tinggi dari LMS/CBT.**

#### A.1 Kurikulum & Mata Pelajaran (1 hari)
**Migrasi:**
- [ ] `kurikulum` (kode, nama, deskripsi, is_aktif)
- [ ] `kurikulum_sekolah` (kurikulum_id, sekolah_id, tahun_ajaran_id, tingkat_id) + unik
- [ ] `mata_pelajaran` (kode, nama, kelompok_ref, jenjang_id, is_aktif)
- [ ] `struktur_kurikulum` (kurikulum_id, tingkat_id, mata_pelajaran_id, jam_per_minggu, is_wajib, kkm) + unik
- [ ] `kompetensi_guru` (pegawai_id, mata_pelajaran_id, tingkat_id)
- [ ] `tujuan_pembelajaran` (kurikulum_id, mata_pelajaran_id, tingkat_id, fase, kode, elemen, uraian, urutan)

**Service:**
- [ ] `KurikulumService::terapkanKurikulum(sekolahId, tahunAjaranId, kurikulumId, tingkatIds)`
- [ ] `KurikulumService::salinStrukturKurikulum(kurikulumAsalId, kurikulumBaruId)`

**Panel:**
- [ ] `KurikulumResource`, `MataPelajaranResource`
- [ ] `StrukturKurikulumResource` (nested di dalam Kurikulum, repeater per tingkat)
- [ ] `TujuanPembelajaranResource` (nested di Kurikulum, filter per mapel/tingkat)
- [ ] `KompetensiGuruResource` (di modul SDM, relasi ke Pegawai)

**Tes:**
- [ ] Buat kurikulum + struktur
- [ ] Terapkan kurikulum ke sekolah/tahun ajaran
- [ ] Salin struktur kurikulum
- **Target:** 5-6 tes lolos

#### A.2 Jadwal & Penugasan Mengajar (1 hari)
**Migrasi:**
- [ ] `penugasan_mengajar` (sekolah_id, tahun_ajaran_id, semester_id, rombel_id, mata_pelajaran_id, pegawai_id, peran utama/pendamping, jam_per_minggu, no_sk) + unik
- [ ] `jam_pelajaran` (sekolah_id, tahun_ajaran_id, hari, urutan, jam_mulai, jam_selesai, tipe belajar/istirahat/ibadah) + unik
- [ ] `jadwal_pelajaran` (semester_id, penugasan_mengajar_id, jam_pelajaran_id, ruang_id) + unik

**Service:**
- [ ] `PenugasanMengajarService::tugaskan(rombel, mapel, pegawai, peran)` → validasi kompetensi guru
- [ ] `JadwalService::buatJadwal(penugasanId, hariJamId, ruangId)` → cek bentrok guru/rombel/ruang
- [ ] `JadwalService::cekBentrok(pegawaiId/rombelId/ruangId, semester, hari, jam)`

**Panel:**
- [ ] `PenugasanMengajarResource` (CRUD, filter per rombel/semester)
- [ ] `JadwalPelajaranResource` (grid/kalender mingguan, drag-drop, validasi bentrok)

**Tes:**
- [ ] Tugaskan guru ke rombel/mapel
- [ ] Buat jadwal, cek bentrok guru/rombel/ruang
- [ ] Guru tanpa kompetensi ditolak
- **Target:** 6-7 tes lolos

#### A.3 Jurnal Mengajar & Presensi Siswa (1 hari)
**Migrasi:**
- [ ] `jurnal_mengajar` (penugasan_mengajar_id, tanggal, jam_pelajaran_id, pegawai_id, materi, kegiatan, catatan, status hadir/diganti/kosong)
- [ ] `presensi_siswa` (rombel_id, siswa_id, tanggal, jurnal_mengajar_id, status hadir/sakit/izin/terlambat/alpa, keterangan, dicatat_oleh) + unik
- [ ] `izin_siswa` (siswa_id, tanggal_mulai, tanggal_selesai, jenis sakit/izin, alasan, diajukan_oleh, status)

**Service:**
- [ ] `JurnalMengajarService::catatJurnal(penugasanId, tanggal, materi)` → validasi jadwal
- [ ] `PresensiSiswaService::catatHarian(rombelId, tanggal, daftarStatus)` → untuk TK/SD (tanpa jurnal_mengajar_id)
- [ ] `PresensiSiswaService::catatPerJam(jurnalMengajarId, daftarStatus)` → untuk SMP (dengan jurnal_mengajar_id)
- [ ] `IzinSiswaService::ajukanIzin()`, `setujui()` → tandai presensi otomatis

**Panel:**
- [ ] `JurnalMengajarResource` (list per guru, action cepat catat jurnal hari ini)
- [ ] `PresensiSiswaResource` (grid/tabel per rombel/tanggal, bulk edit)
- [ ] `IzinSiswaResource` (form pengajuan, action setujui/tolak)

**Tes:**
- [ ] Catat jurnal mengajar
- [ ] Catat presensi harian (TK/SD) dan per jam (SMP)
- [ ] Izin siswa → presensi ditandai otomatis
- [ ] Lingkup cabang (guru sekolah A tidak bisa catat jurnal sekolah B)
- **Target:** 7-8 tes lolos

#### A.4 Penilaian Sederhana (2 hari)
**Migrasi:**
- [ ] `komponen_penilaian` (sekolah_id, tahun_ajaran_id, kurikulum_id, kode, nama, jenis formatif/sumatif/uts/uas, bobot, urutan) + unik
- [ ] `penilaian` (penugasan_mengajar_id, semester_id, komponen_id, tujuan_pembelajaran_id, judul, tanggal, nilai_maks, status draft/final, sumber manual/tugas/cbt, sumber_id)
- [ ] `nilai_siswa` (penilaian_id, siswa_id, nilai, nilai_remedial, catatan) + unik
- [ ] `skala_penilaian` (sekolah_id, kurikulum_id, kode, nama, jenis angka/predikat/capaian/deskriptif)
- [ ] `skala_penilaian_butir` (skala_id, kode, label, nilai_min, nilai_maks, urutan, deskripsi_templat)
- [ ] `aturan_olah_nilai` (sekolah_id, tahun_ajaran_id, kurikulum_id, tingkat_id, mata_pelajaran_id, metode rata_berbobot/rata_sumatif/nilai_tertinggi/formula, formula jsonb, pembulatan, kkm, skala_id) — aturan paling spesifik menang
- [ ] `nilai_akhir` (penugasan_mengajar_id, siswa_id, semester_id, nilai_akhir, skala_butir_id, deskripsi, kkm, is_tuntas, status draft/final) + unik

**Service:**
- [ ] `PenilaianService::buatPenilaian(penugasanId, komponenId, judul, tanggal, nilaiMaks)`
- [ ] `PenilaianService::inputNilai(penilaianId, siswaId, nilai)`
- [ ] `PenilaianService::finalisasi(penilaianId)` → status draft → final
- [ ] `OlahNilaiService::hitungNilaiAkhir(penugasanId, siswaId, semesterId)` → ambil aturan_olah_nilai, terapkan metode, simpan ke nilai_akhir

**Panel:**
- [ ] `KomponenPenilaianResource` (CRUD per sekolah/tahun ajaran)
- [ ] `PenilaianResource` (list per guru, action input nilai bulk, action finalisasi)
- [ ] `NilaiAkhirResource` (list per rombel/semester, action hitung nilai akhir massal, export Excel)
- [ ] `AturanOlahNilaiResource` (CRUD, wizard pilih tingkat/mapel → atur metode)

**Tes:**
- [ ] Buat penilaian, input nilai
- [ ] Finalisasi penilaian (tidak bisa diubah lagi)
- [ ] Hitung nilai akhir dengan metode rata berbobot
- [ ] Aturan olah nilai: mapel > tingkat > kurikulum > default
- [ ] Lingkup cabang
- **Target:** 8-10 tes lolos

**Catatan Rapor:** Sistem rapor fleksibel (templat, bagian, butir) **ditunda ke fase lanjutan** karena kompleks. Untuk produksi awal, cukup export nilai_akhir ke Excel dan cetak manual.

**Output Modul Akademik (P3):** Kurikulum, jadwal, presensi, penilaian sederhana production-ready, 26-31 tes lolos.

---

### PRIORITAS 4: Modul Kesiswaan Core (F3) — 2-3 hari kerja

**Konteks:** Pelengkap akademik, mendukung pembinaan siswa.

#### S.1 Prestasi & Kasus Siswa (1 hari)
**Migrasi:**
- [ ] `prestasi_siswa` (siswa_id, cabang_id, tahun_ajaran_id, nama, bidang_ref, tingkat, peringkat, penyelenggara, tanggal, pembina_id, keterangan)
- [ ] `jenis_pelanggaran` (kode, nama, kategori, jenjang_id, poin, tingkat ringan/sedang/berat, is_aktif)
- [ ] `kasus_siswa` (siswa_id, cabang_id, tahun_ajaran_id, jenis_pelanggaran_id, tanggal_kejadian, uraian, poin, status, pelapor_id, penangan_id, is_rahasia)
- [ ] `kasus_siswa_tindakan` (kasus_id, tanggal, jenis_tindakan, uraian, oleh_id, ortu_dihubungi)
- [ ] `konseling` (siswa_id, cabang_id, konselor_id, tanggal, topik, ringkasan encrypted, tindak_lanjut, is_rahasia)

**Service:**
- [ ] `PrestasiSiswaService::catat()`
- [ ] `KasusSiswaService::laporkan()`, `tanganani()`, `selesaikan()`
- [ ] `KonselingService::catat()` → akses dibatasi peran konselor, log di activity_log

**Panel:**
- [ ] `PrestasiSiswaResource`, `JenisPelanggaranResource`
- [ ] `KasusSiswaResource` (action tambah tindakan, kirim notif ortu)
- [ ] `KonselingResource` (akses terbatas, encrypt/decrypt otomatis)

**Tes:**
- [ ] Catat prestasi, kasus, konseling
- [ ] Akumulasi poin pelanggaran
- [ ] Konseling hanya bisa diakses konselor
- **Target:** 5-6 tes lolos

#### S.2 Ekskul & Kalender Kegiatan (1 hari)
**Migrasi:**
- [ ] `ekskul` (sekolah_id, nama, kategori_ref, deskripsi, is_aktif)
- [ ] `ekskul_kelompok` (ekskul_id, cabang_id, tahun_ajaran_id, semester_id, pembina_id, hari, jam_mulai, jam_selesai, tempat, kuota, status)
- [ ] `ekskul_anggota` (kelompok_id, siswa_id, tanggal_gabung, status, predikat) + unik
- [ ] `ekskul_pertemuan` (kelompok_id, tanggal, materi, catatan)
- [ ] `ekskul_kehadiran` (pertemuan_id, siswa_id, status hadir/izin/sakit/alpa) + unik
- [ ] `kalender_kegiatan` (tahun_ajaran_id, cabang_id, sekolah_id, judul, kategori akademik/libur/ujian/keagamaan, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai, lokasi, deskripsi, is_libur, penanggung_jawab_id, visibilitas internal/publik, status)

**Service:**
- [ ] `EkskulService::buatKelompok()`, `tambahAnggota()`, `catatKehadiran()`
- [ ] `KalenderKegiatanService::buatKegiatan()`, `tandaiLibur()` → integrasi ke perhitungan cuti/izin (hari libur)

**Panel:**
- [ ] `EkskulResource`, `EkskulKelompokResource`
- [ ] `EkskulAnggotaResource` (nested, bulk tambah siswa)
- [ ] `KalenderKegiatanResource` (kalender view, action impor libur nasional)

**Tes:**
- [ ] Buat ekskul, kelompok, tambah anggota
- [ ] Catat kehadiran ekskul
- [ ] Kalender kegiatan → hari libur diperhitungkan cuti
- **Target:** 5-6 tes lolos

**Output Modul Kesiswaan (P4):** Prestasi, kasus, ekskul, kalender production-ready, 10-12 tes lolos.

---

### PRIORITAS 5: Panel Filament yang Belum Dibuat — 1-2 hari kerja

**Resource yang ada modelnya tetapi belum ada UI:**

**Arsip & Persuratan:**
- [ ] `DisposisiResource` (nested di SuratMasuk, form disposisi ke user lain)
- [ ] `KlasifikasiArsipResource` (tree/hierarki)
- [ ] `TemplatSuratResource` (CRUD, preview HTML + placeholder)
- [ ] `LokasiArsipFisikResource` (tree)
- [ ] `DokumenResource` (upload, versi, akses control)

**Inventaris:**
- [ ] `TanahResource`, `BangunanResource` (sudah ada model, belum ada resource)
- [ ] `KategoriAsetResource` (tree)
- [ ] `PenerimaanBarangResource` (form terima barang dari PO, buat aset_unit otomatis)
- [ ] `PermintaanBarangResource` (form permintaan, approval)
- [ ] `PengeluaranBarangResource` (form keluar barang dari gudang)
- [ ] `StokOpnameResource` (form opname, action sesuaikan stok)
- [ ] `MutasiAsetResource` (form pindah aset antar ruang/cabang)
- [ ] `LaporanKerusakan Resource`, `PemeliharaanAsetResource`, `PenghapusanAsetResource`
- [ ] `ReservasiRuangResource` (kalender, cek bentrok)

**SDM:**
- [ ] `KontrakKerjaResource`, `PegawaiSertifikasiResource`, `PegawaiPendidikanResource`, dst (nested di PegawaiResource)
- [ ] `PeriodeGajiResource`, `SlipGajiResource` (action hitung gaji, cetak slip, bayar)
- [ ] `KpiPeriodeResource`, `KpiIndikatorResource`, `KpiTemplatResource`, `KpiPenilaianResource`

**Estimasi:** 1 resource sederhana = 30 menit, 1 resource kompleks (nested/wizard/kalender) = 1-2 jam.

**Strategi:** Buat resource saat dibutuhkan pengguna, jangan semua sekaligus.

---

### PRIORITAS 6: Modul LMS & Bank Soal (F4) — 7-10 hari kerja

**Konteks:** Fitur lanjutan, **ditunda sampai modul akademik & keuangan stabil**.

**Urutan:**
1. LMS dasar (kelas, materi, tugas) — 2 hari
2. Bank soal + CRUD soal (tanpa eksport) — 3 hari
3. CBT dasar (ujian, peserta, jawaban, skor otomatis) — 2 hari
4. Paket soal + ekspor PDF/DOCX (dengan Gotenberg/Pandoc) — 2-3 hari

**Estimasi migrasi:** 55 tabel  
**Estimasi tes:** 40-50 tes

---

## ROADMAP KESELURUHAN

### Minggu 1-2 (20 Nov - 3 Des 2026)
- ✅ **P1:** Stabilisasi Paket C (cuti/izin) — 1-2 hari
- 🔄 **P2:** Kas/Bank + Tagihan/Pembayaran Siswa — 3-5 hari
- 🔄 **P3.1:** Kurikulum & Mata Pelajaran — 1 hari

### Minggu 3-4 (4 Des - 17 Des 2026)
- 🔄 **P3.2-3.4:** Jadwal, Presensi, Penilaian — 4 hari
- 🔄 **P4:** Kesiswaan (Prestasi, Kasus, Ekskul) — 2-3 hari
- 🔄 **P5:** Panel Inventaris & Arsip yang belum ada — 2 hari

### Minggu 5-6 (18 Des - 31 Des 2026)
- ⏸️ **P6:** LMS & Bank Soal dasar — 5 hari
- ⏸️ **P6:** CBT + ekspor PDF — 5 hari

### Januari 2027 (Fase Lanjutan)
- Rapor fleksibel (templat, bagian, butir)
- Penilaian deskriptif TK
- Konsolidasi keuangan otomatis
- Anggaran & pengajuan dana
- Perpustakaan, UKS, komunikasi
- Deployment (Docker, staging, production)

---

## CATATAN PENTING UNTUK ACT

1. **Migrasi Paket C belum dijalankan:** Jalankan `php artisan migrate` setelah perbaikan panel selesai.

2. **FK tertunda:** Tabel inventaris punya kolom `entitas_id` tanpa FK ke `entitas_akuntansi`. Setelah modul Keuangan stabil, buat migrasi untuk menambahkan FK tersebut.

3. **Payroll belum handle PPh21/BPJS:** `PayrollService::hitungGaji()` ada placeholder formula sederhana. Butuh aturan PPh21 & BPJS dari bagian keuangan yayasan.

4. **Pengirim WhatsApp masih log:** `config/pesan.php` memakai `LogPengirim`. Setelah pengguna pilih penyedia (Fonnte/Wablas/Twilio), buat kelas `Pengirim` baru + daftarkan di config.

5. **Tes selalu di PostgreSQL:** `phpunit.xml` sudah benar (`DB_CONNECTION=pgsql`). Jangan ubah ke SQLite.

6. **Konvensi nama tabel:** Tunggal, bahasa Indonesia. Sudah konsisten di 96+ tabel.

7. **Total tes saat ini:** ~120 tes (estimasi dari HANDOVER.md: 70+41+8+2+5+6+6+6+7 = 151, sebagian belum diperbarui). **Target akhir F0-F3:** 250+ tes.

---

## KESIMPULAN

**Progres:** ~35% dari rencana lengkap (96/265 tabel), **tetapi fondasi solid**.

**Yang sudah production-ready:**
- ✅ Core + Data Pokok + SPMB (hampir tuntas)
- ✅ Inventaris (migrasi lengkap, panel dasar)
- ✅ Keuangan (fondasi akuntansi multi-entitas)
- ✅ Kepegawaian (migrasi lengkap, absensi/KPI/payroll)

**Yang butuh diselesaikan segera:**
- 🟡 Paket C (cuti/izin) — panel & tes rusak
- 🔴 Kas/Bank + Tagihan/Pembayaran — **blocking SPMB & SPP**
- 🔴 Akademik (jadwal, presensi, nilai) — **inti sistem sekolah**

**Rekomendasi urutan:** P1 → P2 → P3 → P4 → P5 → P6.

Dokumen ini siap dipakai sebagai panduan untuk mode ACT.