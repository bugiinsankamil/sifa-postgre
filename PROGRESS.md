# Progress Paket C — Cuti/Izin Atomik

## Status: Tahap 1 (C1) Selesai — Migrasi dan Service Helper

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
- ✓ `2026_11_15_055449_buat_tabel_sdm_cuti_izin.php` — tambah `hari_libur`, `mutasi_saldo_cuti`, `pengajuan_*_hari`, kolom `dibawa`, status bertingkat, FK absensi.
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
- ✓ `MutasiSaldoCutiService.php` — saldo atomik dengan carry-over dan mutasi append-only.

### File Belum Selesai

**Service utama:**
- ☐ `CutiService.php` — refactor atomik (ajukan, setujuiAtasan, setujuiFinal, tolak, batalkan) dengan rincian hari, saldo per tahun, validasi benturan.
- ☐ `IzinService.php` — sama untuk izin/sakit/dinas_luar (tanpa saldo).

**Panel:**
- ☐ `JenisCutiResource.php`, `SaldoCutiResource.php`, `HariLiburResource.php` (baru).
- ☐ `PengajuanCutiResource.php`, `PengajuanIzinResource.php` — form hitung otomatis, aksi bertingkat.
- ☐ `Create/EditPengajuanCuti.php`, `Create/EditPengajuanIzin.php` — panggil service, tangkap exception.

**Tes:**
- ☐ `CutiIzinTest.php` — perbarui + tambah tes perhitungan, setengah hari, lintas tahun, benturan, otorisasi, mutasi, atomisitas.
- ☐ `PanelSdmTest.php` — sesuaikan alur bertingkat.

### Langkah Berikutnya

1. **Refactor `CutiService`** — method `ajukan`, `setujuiAtasan`, `setujuiFinal`, `tolak`, `batalkan`; validasi benturan cuti & izin; simpan rincian hari; potong saldo per tahun; semua dalam transaksi terkunci.

2. **Refactor `IzinService`** — sama tanpa saldo/mutasi; validasi benturan izin & cuti.

3. **Buat panel** — Jenis Cuti, Saldo Cuti (read-only + penyesuaian), Hari Libur; perbarui form pengajuan agar hitung hari otomatis.

4. **Tulis tes** — perhitungan, setengah hari, lintas tahun, benturan, otorisasi bertingkat, mutasi, atomisitas, pembatalan.

---

**Total:** 14 file dibuat/diubah, 1 dihapus. **Migrasi belum dijalankan.**
