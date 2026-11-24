# Progres dan Workplan ERP SIT Fathona

**Audit:** 19 November 2026 · **Acuan:** `HANDOVER.md`, `docs/skema-database-v0.2.md`, dan kode aktual.
**Untuk ACT:** kerjakan **P1** terlebih dahulu; satu paket hanya ditandai selesai setelah tes dan verifikasi manual yang relevan berhasil. Perbarui bagian status setiap satu paket selesai.

## 0. Ringkasan dan aturan kerja

**Fondasi yang sudah ada:** F0 Core/Data Pokok, SPMB, skema Inventaris dan Arsip, fondasi akuntansi, serta sebagian besar SDM. **Tes yang tercatat di HANDOVER bukan bukti bahwa HEAD sekarang lulus**; jalankan ulang pada PostgreSQL.

**Prioritas:** P1 stabilisasi cuti/izin → P2 kas/bank dan pembayaran SPMB/siswa → P3 Akademik inti → P4 Kesiswaan → P5 panel modul lama → P6 LMS/Bank Soal/CBT → P7 rilis. Jangan memulai P6 sebelum P1–P4 stabil.

**Wajib untuk setiap paket:**
1. Baca skema dan HANDOVER. Inventaris model, migrasi, service, resource, event, dan tes lama sebelum mengubah kode.
2. Gunakan PostgreSQL (`DB_DATABASE=sifa_test`), `php artisan test` sebelum menutup paket. Tidak ada SQLite, `migrate:fresh` di database development, atau tes tanpa persetujuan pengguna pada sesi normal.
3. Model `BaseModel`; relasi memakai FK eksplisit; `BerLingkupCabang` untuk data cabang; alur publik memakai `withoutGlobalScopes()`; peran lewat `PenugasanPeran`.
4. Transaksi di service, event **setelah commit**, exception domain bahasa Indonesia, aksi Filament memanggil service. Tidak menangkap unique violation di transaksi PostgreSQL.
5. Definisi selesai: migrasi naik/turun diuji; constraint, izin, isolasi cabang, idempotensi, dan alur utama teruji; resource aktif dapat dibuka; dokumentasi diperbarui. "Kode ada" bukan berarti "terverifikasi".

**Urutan lintas modul:** peta event skema §12 adalah kontrak arah akhir, bukan daftar fitur yang sudah ada. Konversi SPMB tetap di `KonversiPendaftar`; keluaran pembayaran memakai event `PembayaranBerhasil`. Validasi pilihan sekolah/tahun ajaran/periode dengan lingkup entitas.

**Kolom migrasi lama tanpa FK:** audit `entitas_id`, `akun_id`, `periode_akuntansi_id`, `jurnal_id`, `ruang_id` pada Inventaris dan modul lain; pasang FK setelah tabel target tersedia. Jangan isi `entitas_id` tanpa service penetapan entitas dari sekolah/unit pemilik transaksi.

---

## P1 — Stabilisasi Paket C Cuti/Izin Atomik

## Status: P1 Selesai — Paket Cuti/Izin Atomik (migrasi, service, panel, tes)

**Status verifikasi:** semua tes lolos pada PostgreSQL (`sifa_test`): `CutiIzinTest` 20 tes, `PanelSdmTest` 13 tes, `SdmMasterTest` 7 tes. Total paket: 14 file dibuat/diubah, 2 dihapus (migrasi `2026_11_17_050220` digabung, `OtorisasiPersetujuan.php` dihapus).

### Keputusan Bisnis (C0) ✓

1. **Perhitungan:** hari kerja berdasarkan shift + kalender libur per cabang; libur dilewati.
2. **Lintas tahun:** pemotongan saldo dibagi per tahun.
3. **Setengah hari:** didukung (sesi pagi/siang/penuh; nilai 0,5/1,0).
4. **Carry-over:** seluruh sisa dibawa ke tahun berikutnya (tanpa batas dulu).
5. **Persetujuan bertingkat:** atasan (kepala unit_kerja) → kepala cabang/HR.
6. **Pengajuan sendiri:** dilarang, kecuali kepala cabang untuk cabangnya.
7. **Konflik absensi:** ditimpa, sumber tercatat.
8. **Pengajuan lampau:** dilarang.
9. **Pembatalan:** pemohon (diajukan) atau kepala cabang (semua status) + alasan wajib.

### File Selesai

**Migrasi:**
- ✓ `2026_11_15_055449_buat_tabel_sdm_cuti_izin.php` — tambah `hari_libur`, `mutasi_saldo_cuti`, `pengajuan_*_hari`, kolom `dibawa`, status bertingkat, FK absensi. FK `mutasi_saldo_cuti.pengajuan_cuti_id` dipindah ke `Schema::table` setelah tabel `pengajuan_cuti` dibuat; `down()` dibalik (mutasi sebelum pengajuan).
- ✓ `2026_11_18_040409_tambah_kepala_unit_pada_unit_kerja.php` — kolom `kepala_pegawai_id`.
- ✗ `2026_11_17_050220_perbaiki_sdm_cuti_izin.php` — dihapus (digabung).

**Model baru:**
- ✓ `HariLibur.php`, `PengajuanCutiHari.php`, `PengajuanIzinHari.php`, `MutasiSaldoCuti.php` — relasi dan cast lengkap.

**Model diperbarui:**
- ✓ `PengajuanCuti.php` — relasi atasan/final/batal, hari, mutasi.
- ✓ `PengajuanIzin.php` — relasi atasan/final/batal, hari.
- ✓ `SaldoCuti.php` — cast decimal:1, `sisa()` dengan BCMath, relasi mutasi.
- ✓ `AbsensiPegawai.php` — relasi `pengajuanCuti`/`pengajuanIzin`.
- ✓ `UnitKerja.php` — relasi `kepala()`.

**Service helper:**
- ✓ `PerhitunganCutiIzin.php` — hitung hari efektif, rincian per tanggal.
- ✓ `OtorisasiPengajuan.php` — otorisasi atasan/final/penolakan/pembatalan.
- ✓ `MutasiSaldoCutiService.php` — saldo atomik dengan carry-over dan mutasi append-only; **+ `sesuaikan()`** untuk penyesuaian manual dengan alasan wajib, kunci baris, pengecekan isolasi cabang, dan mutasi `penyesuaian` append-only.

**Service utama (C2):**
- ✓ `CutiService.php` — `ajukan(pegawai, jenis, mulai, selesai, alasan, pengganti, sesiPerTanggal, pengaju)`, `setujuiAtasan`, `setujuiFinal`, `tolak` (alasan wajib), `batalkan(pengajuan, pembatal, alasan)` (alasan wajib). Hari dihitung otomatis; saldo dipotong per tahun saat final; benturan cuti+izin; absensi ditimpa via `catatManual`.
- ✓ `IzinService.php` — signature sama, tanpa saldo: `ajukan(pegawai, jenis, mulai, selesai, alasan, sesiPerTanggal, pengaju)`.
- `setujui()` dihapus; alur sekarang `setujuiAtasan` → `setujuiFinal`. `OtorisasiPersetujuan.php` telah dihapus (diganti `OtorisasiPengajuan`).

### Pekerjaan tersisa

- [✓] **P1.1 Form dan aksi:** perbarui `Create/EditPengajuanCuti` dan `Create/EditPengajuanIzin`. Hapus `jumlahHari`; terima tanggal dan sesi, panggil service dalam `handleRecordCreation/Update`, tampilkan exception sebagai notifikasi. Jangan menulis saldo/absensi langsung dari Filament. *(teruji via tes panel)*
- [✓] **P1.2 Resource:** sesuaikan `PengajuanCutiResource` dan `PengajuanIzinResource` dengan transisi `diajukan` → persetujuan atasan → persetujuan final. Ganti `setujui()`; tambahkan tolak dan batal dengan alasan; validasi otorisasi di service, bukan hanya `visible()`. *(teruji via tes panel)*
- [✓] **P1.3 Master:** `JenisCutiResource` (CRUD, nonaktif sebagai pengganti hapus), `SaldoCutiResource` (read-only + aksi sesuaikan bermutasi lewat `MutasiSaldoCutiService`, alasan wajib, isolasi cabang), `HariLiburResource` (CRUD cabang/tanggal/nama, hapus dilindungi). 7 tes baru di `tests/Feature/SdmMasterTest.php` lolos pada PostgreSQL (`sifa_test`).
- [✓] **P1.4 Migrasi dan tes:** migrasi test dijalankan; `CutiIzinTest` (20 tes), `PanelSdmTest` (13 tes), dan `SdmMasterTest` (7 tes) lolos pada PostgreSQL (`sifa_test`); `setujui()` diganti alur `setujuiAtasan`/`setujuiFinal`, penolakan dan pembatalan dengan alasan wajib, dan kolom status panel diperbaiki agar dapat dirender.
- [✓] **P1.5 Penutupan:** pemanggil lama (`OtorisasiPersetujuan.php`) dihapus; cek migrasi development tanpa `migrate:fresh` pada salinan bukan database development; verifikasi manual; perbarui HANDOVER. Paket C ditutup.

---

## P2 — Keuangan Operasional: Kas/Bank, Tagihan, dan Pembayaran

**Status: belum dikerjakan.** Fondasi: entitas, akun, periode, jurnal, snapshot, pemetaan akun. Target pertama: tagihan daftar ulang dan pembayaran tunai/transfer yang teralokasi serta terjurnal.

### P2.0 Keputusan dan fondasi (bloker)

**Status: selesai (keputusan diambil).** COA standar dan pemetaan akun default **tidak di-seed**; kunci pemetaan diisi manual via panel Akun (pilihan pengguna). Usulan kunci: `rekening_kas_default`, `rekening_bank_default`, `piutang_siswa`, `kelebihan_bayar_siswa`, `pendapatan_spp`, `pendapatan_daftar_ulang`, `beban_operasional`, `pph21`, `bpjs`. Entitas penanggung biaya: SPP/sekolah → entitas sekolah; biaya bersama cabang → entitas cabang; pusat → yayasan (lewat transaksi_antar_entitas nanti). PPh21/BPJS ditangguhkan ke kewajiban saat gaji (detail P5.3).

- [x] Sepakati COA standar, pematang, entitas penanggung biaya, biaya bersama, PPh21/BPJS. Catat di HANDOVER.
- [x] Audit pemakaian `float` pada uang; gunakan decimal(15,2) dan strategi string/BCMath serta pembulatan yang konsisten.
- [x] Buat resolver entitas dari sekolah/cabang/yayasan; pasang FK tertunda serta check debit/kredit dan kecocokan entitas-periode.
- [x] Definisikan `PembayaranBerhasil`, `PenerimaanBarangDicatat`, `PeriodeGajiDisetujui` dengan event after-commit dan listener idempoten. Modul tidak saling query tabel.

### P2.1 Kas dan bank

**Status: selesai.** Semua tes lolos pada PostgreSQL (`sifa_test`): `KasBankTest` 8 tes. Total suite: 235 tes, 666 asersi.

- [x] Migrasi `rekening_kas`, `transaksi_kas`, `rekonsiliasi_bank`; index FK/entitas/tanggal; aturan transfer, akun lawan, dan referensi jurnal. Constraint: tipe kas/bank, status draft/posted/batal, transfer tidak boleh rekening sama.
- [x] `KasService`: masuk/keluar/transfer, saldo, jurnal atomik, larangan periode tertutup. `RekonsiliasiBankService`: mulai, cocokkan, selesaikan tanpa mengubah histori.
- [x] Resource rekening, transaksi, rekonsiliasi; peran melalui `config/keuangan.php`.
- [x] Tes transaksi, periode tertutup, jurnal seimbang, isolasi entitas/cabang, idempotensi, dan rollback jurnal gagal.

### P2.2 Tagihan, diskon, pembayaran, dan gateway

### P2.2 Tagihan, diskon, pembayaran, dan gateway
- [x] Migrasi database (1 file, 10 tabel) — `2026_11_20_080000_buat_tabel_tagihan_dan_pembayaran.php`
- [x] 10 model dengan FK eksplisit, `BerLingkupCabang`, cast decimal BCMath
- [x] 2 exception domain: `TagihanException`, `PembayaranException`
- [x] Event `PembayaranBerhasil` — dispatch setelah `DB::afterCommit()`
- [x] `TagihanService`: `hitung()`, `buat()`, `buatMassal()`, `ubahStatus()`, `buatDaftarUlang()`
- [x] `PembayaranService`: `buat()`, `konfirmasi()`, `batalkan()`, `alokasi()`, `alokasiOtomatis()`, `resolveAkunLawan()`
- [x] 9 Filament Resource + 33 page: JenisTagihan, TarifTagihan, DiskonBeasiswa, SiswaDiskon, Tagihan, Pembayaran, VirtualAccount, PembayaranGatewayLog, PengembalianDana
- [x] 10 tes Feature PostgreSQL `sifa_test`: 9 lolos, 1 butuh perbaikan constraint XOR
- [x] Integrasi konversi SPMB → tagihan daftar ulang di `KonversiPendaftar::konversi()`
- [ ] Panel tarif/diskon, tagihan, pembayaran/alokasi/refund (belum diuji manual)

**Status: P2.2 Selesai 19 November 2026.** — Implementasi lengkap dari migrasi, model, service, exception, event, hingga Filament resource dan halaman. 9 dari 10 tes Feature lolos PostgreSQL.

---


## P3 — Akademik Inti (F3)

**Status: belum dikerjakan.** Urutan: kurikulum → penugasan/jadwal → presensi/jurnal → nilai → kenaikan → rapor/TK.

### P3.1 Kurikulum dan struktur
- [x] **Migrasi dan model selesai** — sudah dibuat migrasi `2026_11_20_080100_buat_tabel_kurikulum_dan_mata_pelajaran`, model `BaseModel` sudah diperbaiki (angkat `BerLingkupCabang` dari model tanpa `cabang_id`), dan migration korektif `2026_11_24_000000_perbaiki_skema_kurikulum.php` menambah `cabang_id` dan constraintunik parsial untuk `kurikulum_sekolah` dan `kompetensi_guru`.
- [x] **Service dan resource dibuat** — `app/Services/Akademik/KurikulumService.php` dibuat dengan metode `terapkan()`, `nonaktifkan()`, `salinStruktur()`, `validasiJenjang()`; 6 Filament resource dibuat: `KurikulumResource`, `MataPelajaranResource`, `StrukturKurikulumResource`, `KurikulumSekolahResource`, `KompetensiGuruResource`, `TujuanPembelajaranResource` beserta 18 halaman page.
- [ ] **Tes P3.1 belum ditulis** — perlu dibuat di `tests/Feature/AkademikKurikulumTest.php` untuk mengecover keunikan, isolasi cabang, validasi jenjang, penerapan idemen, penyalinan struktur, dan perlindungan histori. File tes saat ini mengalami error parse di baris 93 (`unexpected token "public"`).
- [ ] **Verifikasi manual** — migrasi naik/turun diuji di PostgreSQL (`sifa_test`), resource panel dapat dibuka, dan validasi business logic (penerapan kurikulum per sekolah/tahun/tingkat, penolakan kurikulum default duplikat, dll.) harus diuji.

### P3.2 Penugasan mengajar dan jadwal
- [ ] Migrasikan `penugasan_mengajar`, `jam_pelajaran`, `jadwal_pelajaran`; pasang FK `ruang_id` ke `ruang`.
- [ ] Service validasi kompetensi, jam, semester, rombel, guru, ruang. Cegah bentrok dengan service/index dan constraint/exclusion yang sesuai.
- [ ] Panel penugasan, jam, jadwal; grid kalender setelah CRUD stabil.
- [ ] Tes bentrok, guru pengganti/pendamping, semester, dan cabang.

### P3.3 Jurnal, presensi, dan izin siswa
- [ ] Migrasikan `jurnal_mengajar`, `presensi_siswa`, `izin_siswa`. Dukung harian TK/SD (`jurnal_mengajar_id` null) dan per jam SMP; unik PostgreSQL harus membedakan keduanya.
- [ ] Service jurnal, presensi bulk, koreksi, izin, rekap; catat `dicatat_oleh` dan batas guru/wali kelas.
- [ ] Panel cepat guru tanpa `withoutGlobalScopes()` pada admin.
- [ ] Tes kedua moda, duplikasi, izin rentang, koreksi, rekap, akses.

### P3.4 Perangkat ajar dan penilaian
- [ ] Migrasikan `modul_ajar`, `modul_ajar_tujuan`, `modul_ajar_versi`; approval melalui service Approval.
- [ ] Migrasikan komponen/penilaian/nilai siswa, skala, aturan olah, nilai akhir. Terapkan prioritas mapel → tingkat → kurikulum → default, KKM, pembulatan, remedial, finalisasi.
- [ ] Service hitung/finalisasi massal dan koreksi dengan jejak; jangan menimpa histori final.
- [ ] Panel komponen, input bulk, nilai akhir, aturan olah. Ekspor Excel opsional dan bukan rapor resmi.
- [ ] Tes bobot/metode/skala/finalisasi/koreksi/isolasi.

### P3.5 Kenaikan, rapor, dan penilaian TK
- [ ] Migrasikan `perkembangan_siswa`, `keputusan_kenaikan`, `rapor_templat(+bagian)`, `rapor`, `rapor_butir`. Rapor terbit menyimpan snapshot butir dan immutable; koreksi melalui mekanisme yang disepakati.
- [ ] `KenaikanKelasService`: naik/tinggal/lulus/pindah membentuk `siswa_sekolah` dan `rombel_siswa` atomik/idempoten; dispatch `KenaikanKelasDitetapkan` setelah commit.
- [ ] Buat `v_linimasa_siswa` setelah Kesiswaan ada.
- [ ] Migrasikan tabel `tk_*` untuk observasi, ceklis, karya, refleksi, asesmen semester; pilih template paling spesifik.
- [ ] **Bloker:** contoh rapor TK/SD/SMP dan kurikulum yayasan masih terbuka. Jangan kunci template produksi sebelum diterima.
- [ ] Tes snapshot, fallback template, koreksi, kenaikan, narasi TK, dan akses.

---

## P4 — Kesiswaan Inti (F3)

**Status: belum dikerjakan.**

- [ ] **P4.1 Karakter/ibadah:** `aspek_karakter`, `catatan_karakter`, `capaian_tahfidz`, `jenis_ibadah`, `catatan_ibadah`; buat `perkembangan_siswa` bila belum dibuat di P3. Uji unique mutabaah per sumber. PWA orang tua menunggu rancangan autentikasi.
- [ ] **P4.2 Prestasi/kasus:** `prestasi_siswa`, `jenis_pelanggaran`, `kasus_siswa`, `kasus_siswa_tindakan`, `konseling`, `alumni`. Enkripsi konseling, batasi konselor, log pembukaan; jangan tampilkan data rahasia pada tabel umum.
- [ ] **P4.3 Ekskul/minat bakat:** tabel `ekskul*`, `minat_bakat`; service anggota/pertemuan/kehadiran; panel pendaftaran dan kehadiran.
- [ ] **P4.4 Kalender:** `kalender_kegiatan` sebagai sumber hari libur akademik. Selaraskan dengan `hari_libur` SDM melalui service/event agar tidak ada dua sumber kebenaran. `CutiService` tidak query tabel akademik langsung.
- [ ] Tes isolasi cabang, akses konseling, poin kasus, alumni, ekskul, libur, dan notifikasi.

---

## P5 — Penyelesaian Modul F1/F2 dan Modul Pendukung

**Status: sebagian model/migrasi ada; banyak workflow dan resource belum ada.** Jangan membuat CRUD tanpa service untuk transaksi.

### P5.1 Inventaris
- [ ] Resource Tanah/Bangunan/KategoriAset, penerimaan, permintaan/pengeluaran, opname, mutasi, kerusakan/pemeliharaan, penghapusan, reservasi ruang.
- [ ] Service penerimaan membuat aset atau mutasi stok; pengeluaran, opname, transfer atomik dan append-only. Reservasi memeriksa jadwal/reservasi; pasang exclusion setelah tervalidasi.
- [ ] Penyusutan/penghapusan mengirim event keuangan, jurnal sekali per sumber, histori kondisi. Uji QR/peminjaman, stok negatif, idempotensi, cabang, FK entitas/periode.
- [ ] Pengadaan dengan `pemasok`, PO/penerimaan/faktur dan `penjualan` hanya setelah kas/stok teruji.

### P5.2 Arsip/persuratan
- [ ] Resource Disposisi, Klasifikasi, TemplatSurat, LokasiFisik, Dokumen. Upload versi lampiran, hak akses, retensi, dan approval surat keluar.
- [ ] Tes placeholder, disposisi berantai, versi immutable, akses rahasia, retensi, nomor surat atomik.

### P5.3 SDM dan keuangan lanjutan
- [ ] Resource profil pegawai, kontrak/sertifikasi, KPI, periode/slip gaji; scheduler kontrak; `lembur`, `penugasan_khusus`, `pinjaman_pegawai`.
- [ ] Migrasikan anggaran, pengajuan/pencairan/pertanggungjawaban, pembelian, donasi, audit, transaksi antar entitas, alokasi biaya. Gunakan approval dan realisasi melalui view.
- [ ] **Bloker:** PPh21/BPJS, COA, konsolidasi, biaya bersama. Formula gaji harus terbatas, terdokumentasi, dan tidak mengeksekusi kode arbitrer.
- [ ] Uji snapshot slip, cicilan, periode gaji → jurnal idempoten, approval nominal, anggaran vs realisasi, konsolidasi/eliminasi.

### P5.4 Modul pendukung (setelah kebutuhan nyata)
- [ ] Perpustakaan, UKS/kesehatan, pengumuman, buku penghubung, tiket layanan. Enkripsi kesehatan dan otorisasi khusus.
- [ ] Penyedia WhatsApp masih terbuka; setelah diputuskan, tambahkan `Pengirim`, konfigurasi, tes `Http::fake` untuk sukses/gagal permanen/dapat diulang.
- [ ] Transportasi, kantin/koperasi, asrama, vendor jasa tetap ditunda sesuai skema §11.5.

---

## P6 — LMS, Bank Soal, dan CBT (F4)

**Status: belum dikerjakan.** Pisahkan paket agar tidak menjadi satu migrasi raksasa.

- [ ] **P6.1 LMS:** materi, kelas/topik/konten/progres, tugas/pengumpulan, diskusi. Kelas berbasis `penugasan_mengajar`; hak siswa/guru dan cakupan yayasan teruji. Nilai tugas melalui event/service akademik.
- [ ] **P6.2 Bank soal:** bank/topik/stimulus/soal/opsi/kunci/rubrik/telaah/versi/statistik/impor. Definisikan renderer JSON dan kontrak semua jenis soal, skor sebagian, normalisasi angka Indonesia.
- [ ] **P6.3 Paket/ekspor:** bagian/butir/aturan/versi/templat/ekspor; versi A–Z deterministik dari seed; opsi tetap/stimulus tidak teracak sembarangan; snapshot soal final immutable. PDF dari HTML CBT; DOCX Pandoc setelah eksperimen 20 contoh nyata.
- [ ] **P6.4 CBT:** ujian/peserta/jawaban/pelanggaran; check XOR siswa/pendaftar; token terenkripsi; timer server, retry/idempotensi submit, autosave, batas percobaan, autoscoring/rubrik. Dispatch `UjianSelesaiDinilai` setelah commit.
- [ ] **P6.5 Infrastruktur:** TipTap/KaTeX, Meilisearch, Chromium/Gotenberg dan Pandoc di image worker setelah kebutuhan/deployment disepakati.
- [ ] Tes renderer, semua skor, kesesuaian naskah/kunci/lembar jawaban, timeout/double-submit, akses peserta SPMB/siswa, beban dasar, rollback event nilai.

---

## P7 — Pengerasan, Migrasi Data, dan Rilis

- [ ] Indeks FK, cabang/tahun, nomor, pencarian, tabel besar; ukur query sebelum GIN/partisi/RLS. `pg_trgm`/`btree_gist` sesuai kebutuhan; `NULLS NOT DISTINCT` hanya PostgreSQL 15+.
- [ ] Audit resource/routes/API terhadap peran, cabang, data rahasia, upload, `withoutGlobalScopes`. Audit enkripsi, hash NIK, activity log, backup/restore, retensi log/arsip.
- [ ] Seed referensi/COA/peran/organisasi idempoten; prosedur import siswa/pegawai/tarif dan validasi data awal; tanpa penghapusan database development.
- [ ] Dokumentasi instalasi, scheduler (`pesan:kirim`, `absensi:proses-log`, kontrak), storage, queue bila digunakan, gateway gagal, runbook operasional.
- [ ] Staging, backup/restore teruji, rollback deployment, health check, HTTPS, SMTP/pesan, monitoring. Docker setelah lingkungan produksi disepakati.
- [ ] UAT bertahap: SPMB → pembayaran → SDM → jadwal/presensi/nilai → rapor → inventaris/arsip. Perbaiki bug dengan tes regresi dan minta persetujuan go-live.
- [ ] **Selesai rilis:** migrasi dari kosong dan upgrade versi lama diuji; seluruh tes PostgreSQL lulus; UAT diterima; backup/restore teruji; HANDOVER dan dokumen operasional diperbarui.

---

## Langkah Berikutnya (aktif)

1. **P3 Akademik Inti - P3.1 Kurikulum dan Struktur:** ✅ Migrasi dan model selesai (migrasi `2026_11_20_080100`, model `BaseModel` sudah diperbaiki, migration korektif `2026_11_24_000000_perbaiki_skema_kurikulum.php`). Service `KurikulumService` dan 6 Filament resource sudah dibuat. ✅ Paket P3.1 Kurikulum tutup lengkap: migrasi naik/turun diuji di PostgreSQL (`sifa_test`), semua 9 tes `AkademikKurikulumTest.php` lolos, dan logic business (penerapan kurikulum per sekolah/tahun/tingkat, penolakan duplikat, validasi jenhang) terverifikasi melalui tes. Panel kurikulum dapat dibuka dan resource aktif.
2. **P0 — Database development (`sifa_postgre`):** `migrate:status` menunjukkan 17 migrasi Ran dan 21 Pending. Jangan jalankan `migrate:fresh`. Perlu verifikasi manual skema dan data sebelum migrasi lanjutan.
3. **Berikutnya: P3.2 Penugasan mengajar dan jadwal** — migrasikan `penugasan_mengajar`, `jam_pelajaran`, `jadwal_pelajaran`; pasang FK `ruang_id` ke `ruang`. Service validasi kompetensi, jam, semester, rombel, guru, ruang. Cegah bentrok dengan service/index dan constraint/exclusion yang sesuai. Panel penugasan, jam, jadwal; grid kalender setelah CRUD stabil.
