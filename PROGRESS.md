# Progress Paket C — Cuti/Izin Atomik

## Status: C1 + C2 Selesai — Migrasi, Service Helper, CutiService, IzinService (panel dan tes belum disesuaikan)

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

**Service utama (C2):**
- ✓ `CutiService.php` — `ajukan(pegawai, jenis, mulai, selesai, alasan, pengganti, sesiPerTanggal, pengaju)`, `setujuiAtasan`, `setujuiFinal`, `tolak` (alasan wajib), `batalkan(pengajuan, pembatal, alasan)` (alasan wajib). Hari dihitung otomatis; saldo dipotong per tahun saat final; benturan cuti+izin; absensi ditimpa via `catatManual`.
- ✓ `IzinService.php` — signature sama, tanpa saldo: `ajukan(pegawai, jenis, mulai, selesai, alasan, sesiPerTanggal, pengaju)`.
- Catatan: `setujui()` dihapus. `OtorisasiPersetujuan.php` sudah tidak dipakai service (hapus setelah panel dan tes dimigrasi).

### File Belum Selesai

**Pemanggil lama yang RUSAK (pakai `setujui()`/signature lama `jumlahHari`):**
- ☐ `Filament/Resources/Sdm/Pages/CreatePengajuanCuti.php`, `CreatePengajuanIzin.php`
- ☐ `Filament/Resources/Sdm/PengajuanCutiResource.php`, `PengajuanIzinResource.php`
- ☐ `tests/Feature/AbsensiAndalTest.php`, `CutiIzinTest.php`, `PanelSdmTest.php`

**Panel:**
- ☐ `JenisCutiResource.php`, `SaldoCutiResource.php`, `HariLiburResource.php` (baru).
- ☐ `PengajuanCutiResource.php`, `PengajuanIzinResource.php` — form hitung otomatis, aksi bertingkat.
- ☐ `Create/EditPengajuanCuti.php`, `Create/EditPengajuanIzin.php` — panggil service, tangkap exception.

**Tes:**
- ☐ `CutiIzinTest.php` — perbarui + tambah tes perhitungan, setengah hari, lintas tahun, benturan, otorisasi, mutasi, atomisitas.
- ☐ `PanelSdmTest.php` — sesuaikan alur bertingkat.

### Langkah Berikutnya

1. **Sesuaikan Create/Resource cuti & izin** ke API baru (hapus input jumlah hari, aksi bertingkat atasan/final/tolak/batalkan dengan alasan).

2. **Buat panel** — Jenis Cuti, Saldo Cuti (read-only + penyesuaian), Hari Libur; perbarui form pengajuan agar hitung hari otomatis.

3. **Tulis tes** — perhitungan, setengah hari, lintas tahun, benturan, otorisasi bertingkat, mutasi, atomisitas, pembatalan.

---

**Total:** 14 file dibuat/diubah, 1 dihapus. **Migrasi belum dijalankan.**
