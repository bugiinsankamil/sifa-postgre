# HANDOVER: ERP Sekolah Islam Terpadu Fathona (SIT Fathona)

> Dokumen ini untuk AI agent (Cline) dan developer yang melanjutkan proyek. Baca seluruhnya sebelum mengubah kode.
> Pengguna berbahasa Indonesia; berkomunikasi dalam bahasa Indonesia. Istilah domain di kode berbahasa Indonesia.
> Tanggal dokumen: 13 November 2026.

---

## 0. Cara memakai dokumen ini (WAJIB dibaca agent)

1. **Jangan berasumsi repo sama persis dengan dokumen ini.** Kode ditulis dalam sesi percakapan tanpa akses ke PHP/PostgreSQL, lalu diserahkan sebagai paket zip yang dipasang pengguna (dengan kemungkinan penyesuaian manual). **Langkah pertama: periksa repo yang sebenarnya** (`git status`, `git log`, tree `app/`, `database/migrations/`, `routes/`) dan cocokkan dengan bagian 3 dan 9.
2. Bagian **9** memuat status verifikasi tiap paket: mana yang sudah dijalankan dan lolos tes oleh pengguna, mana yang diserahkan tetapi **belum diverifikasi**.
3. Bagian **10** adalah tugas berikutnya yang siap dikerjakan, dengan spesifikasi teknis.
4. Skema database lengkap (rencana seluruh modul, ~265 tabel) ada di dokumen terpisah: **"Skema Database ERP SIT Fathona" v0.2** (Claude Doc milik pengguna: `https://claude.ai/artifact/Gbvo2D2X2b2M9DLiYQkR8C`). Minta pengguna mengekspornya sebagai Markdown ke `docs/skema-database-v0.2.md` di repo. Dokumen itu adalah **rencana**; yang sudah menjadi migrasi ada di bagian 4 di bawah.

---

## 1. Ringkasan proyek

- **Klien/pemilik:** Sekolah Islam Terpadu Fathona, 11 cabang, tiap cabang punya jenjang **TK, SD, SMP**.
- **Pengguna yang berinteraksi dengan Anda:** Direktur IT yayasan, **satu-satunya developer** (solo). Pilihan teknologi harus tetap sederhana dan bisa dipelihara satu orang.
- **Tujuan:** ERP yang mencakup data pokok, akademik (kurikulum, LMS, CBT, bank soal, rapor), inventaris, keuangan/akuntansi, SDM/payroll, kesiswaan, SPMB, arsip/persuratan, dan modul pendukung. Organisasi dinamis: konfigurasi lewat data, bukan hardcode.
- **Konteks mendesak:** masa **SPMB (penerimaan murid baru) sedang berjalan sekarang**. Modul SPMB sudah live-ish (formulir publik + panel admin); prioritas pekerjaan mengikuti kebutuhan SPMB.
- **Fase rencana:** F0 Core+Data Pokok (selesai) → F1 SPMB (hampir selesai), Arsip/persuratan, Inventaris → F2 Kepegawaian/SDM + Keuangan → F3 Akademik + Kesiswaan → F4 LMS/Bank soal/CBT.

---

## 2. Arsitektur dan keputusan teknis

### 2.1 Stack (sudah diputuskan pengguna)

| Aspek | Keputusan |
|---|---|
| Framework | **Laravel 13** (anonymous migration classes, `casts()` method, event discovery default aktif) |
| Database | **PostgreSQL 14+** (wajib; memakai partial unique index, expression index, exclusion constraint `btree_gist`, `pg_trgm`, `jsonb`, `FOR UPDATE SKIP LOCKED`, view). **Jangan** menulis kode yang mengandalkan MySQL/SQLite. |
| Admin panel | **Filament 5** (pengguna memasang v5; API resource/tabel/action **sama dengan v4**: `Filament\Schemas\Schema`, `Filament\Actions\*`, `recordActions()`, `Action::schema()`; v5 hanya menambah dukungan Livewire 4) |
| Frontend publik | Blade + CSS inline (tanpa build step), untuk formulir SPMB |
| Auth/otorisasi | `spatie/laravel-permission` dengan **teams** (team = cabang) |
| Audit | `spatie/laravel-activitylog` (diinstal; belum dipakai penuh) |
| Antrean pesan | Tabel DB + perintah artisan terjadwal (bukan queue worker) |
| Direncanakan, belum ada | Redis, Meilisearch, S3-compatible storage, Gotenberg/Chromium (PDF), Pandoc (DOCX), Docker deploy |
| **Diputuskan dibangun sendiri** | LMS, CBT, akuntansi (multi-entitas), bank soal ala ExamView |

### 2.2 Pola arsitektur

- **Modular monolith** secara konsep: satu aplikasi, satu database, kolom `cabang_id` sebagai pemisah data antarcabang. **Catatan jujur:** rencana awal `app/Modules/<Modul>/` **tidak dipakai**. Kode saat ini memakai struktur Laravel standar dengan **penamaan berprefiks modul** (`App\Services\Spmb\*`, `App\Models\Spmb*`, `App\Filament\Resources\Spmb\*`, `App\Events\Spmb\*`). Jangan memindahkan ke `app/Modules` tanpa persetujuan pengguna.
- **Aturan modul:** kode modul A tidak query tabel modul B secara langsung; komunikasi lewat service atau event. FK database lintas modul tetap dipasang demi integritas.
- **Service layer:** logika bisnis di `app/Services/**`; controller, Filament action, dan command tipis. Perubahan status selalu lewat service (bukan `update()` langsung dari UI).
- **Event:** `PendaftarTerdaftar`, `PendaftarTerverifikasi`, `BerkasDitolak` → listener `AntrekanNotifikasiSpmb` (auto-discovery Laravel 11+) → memasukkan pesan ke antrean.

### 2.3 Multi-cabang dan isolasi data

- **Satu database, `cabang_id` eksplisit** di tabel yang sering di-scope (denormalisasi disengaja).
- **FK komposit** menjaga konsistensi: `sekolah` punya `UNIQUE(id, cabang_id)`; tabel yang menyimpan `sekolah_id`+`cabang_id` memakai `FOREIGN KEY (sekolah_id, cabang_id) REFERENCES sekolah(id, cabang_id)`. Pola yang sama untuk `rombel(id,cabang_id,tahun_ajaran_id)`, `spmb_periode(id,cabang_id,sekolah_id)`, `spmb_jalur(id,periode_id)`, dst.
- **Global scope `BerLingkupCabang`** (trait) → `LingkupCabang` (scope) membaca `App\Support\CabangKonteks`.
- **`CabangKonteks`** (scoped singleton): menurunkan cabang yang boleh diakses **langsung dari pengguna yang login** (peran di tabel `model_has_roles`): team `0` ⇒ semua cabang (`null`), selain itu ⇒ daftar `cabang_id`; **pengguna login tanpa peran ⇒ array kosong (tidak melihat apa pun, fail-closed)**; tanpa pengguna login (console, queue, formulir publik) ⇒ `null` (tidak dibatasi). Tidak bergantung pada urutan middleware (penting untuk request Livewire). `tetapkan()` memaksa nilai eksplisit; `lupakan()` mereset cache (dipanggil `PenugasanPeran` setelah peran berubah).
- Model ber-scope: `Sekolah`, `Rombel`, `RombelSiswa`, `SiswaSekolah`, `SpmbPeriode`, `SpmbKontak`, `SpmbPendaftar`, `AntreanPesan`. `Cabang` punya `scopeDapatDiakses()`.
- **Alur publik** (formulir SPMB) harus memakai `withoutGlobalScopes()` pada pemeriksaan yang harus mencakup semua data (duplikat, cek status, dll.).
- **View database (`v_spmb_*`) tidak terkena global scope**: selalu saring manual dengan `cabang_id` yang diizinkan.

### 2.4 Peran dan akses (state otorisasi)

12 peran (enum `App\Enums\Peran`): `sysadmin, yayasan, direktur, kepala_cabang, kepala_sekolah, wakil_kepala_sekolah, pj_program, guru, admin, staf_servis, orang_tua, siswa`.

- Peran dibuat sebagai **peran global** (`roles.cabang_id = NULL`) oleh `PeranSeeder`; **penugasan per team** di `model_has_roles.cabang_id`.
- **Team 0** = tingkat yayasan: `sysadmin`, `yayasan`, `direktur` (`Peran::berlakuGlobal()`). Peran lain wajib dengan `cabangId`.
- Config spatie: `teams = true`, `team_foreign_key = 'cabang_id'`.
- **Jangan** memanggil `assignRole()` langsung; pakai `App\Services\PenugasanPeran::berikan/cabut/punya($user, Peran, ?cabangId)`. Service ini sementara mengganti `PermissionRegistrar::setPermissionsTeamId()` lalu mengembalikannya, dan menolak mencabut sysadmin terakhir.
- `sysadmin` lolos semua pemeriksaan gate lewat `Gate::before` di `AksesServiceProvider` (`User::isSysadmin()`); **tidak** otomatis melewati alur approval berbasis peran.
- **Akses panel Filament:** `User implements FilamentUser, HasName`; `canAccessPanel()` = `is_aktif` && punya salah satu peran di `config/akses.php → peran_panel` (guru/orang_tua/siswa **tidak** boleh). `getFilamentName()` memakai `orang.nama_lengkap` (tabel `users` tidak punya kolom `name`).
- **Resource SPMB:** trait `AksesPanitiaSpmb` (peran di `config/spmb.php → peran_panitia`); hapus hanya sysadmin. **Resource Pesan:** trait `AksesPesan` (`config/pesan.php → peran_lihat`); templat hanya `peran_templat`.
- Asumsi peran yang **belum dikonfirmasi pengguna:** `direktur` = tingkat yayasan; `admin`/`staf_servis` = per cabang; `pj_program` = per sekolah. Ubah di `Peran::berlakuGlobal()` dan `Peran::lingkup()`.
- Izin (permission) per peran **belum didefinisikan** (hanya peran). Tambahkan per modul saat modulnya dibuat.

### 2.5 State management

Tidak ada state frontend global. State yang ada:

| State | Mekanisme |
|---|---|
| Data bisnis | PostgreSQL; operasi multi-langkah di `DB::transaction()`; penomoran dengan `lockForUpdate()` |
| Status pendaftar SPMB | state machine di `AlurSeleksiSpmb` (string status; lihat 5.2) |
| Cabang aktif per request | `CabangKonteks` (scoped singleton) |
| Team spatie | `PermissionRegistrar` team id, diubah sementara oleh `PenugasanPeran` |
| Pendaftar anonim (halaman cek status) | **session**: `spmb.pendaftar_id`, `spmb.waktu` (timestamp), berlaku 30 menit; dibuat setelah nomor pendaftaran + tanggal lahir cocok |
| UI admin | Livewire/Filament (state di komponen; tidak ada store terpisah) |
| Antrean pesan | tabel `antrean_pesan` + status (`antri→diproses→terkirim/gagal/dibatalkan`) |

### 2.6 Konvensi kode dan database (ikuti!)

- **Penamaan:** tabel **tunggal, snake_case, Indonesia** (`siswa`, `rombel_siswa`). `BaseModel::getTable()` = `Str::snake(class_basename)` (tanpa pluralisasi Inggris). Semua model `extends BaseModel`. Tabel framework/package tetap Inggris (`users`, `roles`, `jobs`, `activity_log`).
- **Kolom:** `tanggal_*` (date), `*_pada`/`waktu_*` (timestamp), `jam_*` (time), `*_ref` (kode di tabel `referensi` dengan kategori sama), `is_*` (boolean), uang `decimal(15,2)`, status/jenis = **string** (nilai dikunci di kode/enum, bukan enum PostgreSQL). Hindari singkatan (`tanggal_` bukan `tgl_`).
- **`hasMany`/`hasOne` WAJIB memberi foreign key eksplisit** (mis. `hasMany(SpmbBerkas::class, 'pendaftar_id')`), karena default Eloquent memakai nama class (`spmb_pendaftar_id`). `belongsTo` default sudah benar jika nama relasi = nama kolom tanpa `_id`.
- **Migrasi:** boleh SQL mentah PostgreSQL; nama index ≤ 63 karakter (beri nama eksplisit bila panjang); check constraint via `DB::statement`.
- **Soft delete:** nilai unik tetap menahan baris yang di-soft-delete (disengaja).
- **Data sensitif:** NIK terenkripsi (`encrypted` cast) + `nik_hash` (HMAC-SHA256, unik) lewat trait `MemilikiNikTerenkripsi`; kunci `config('app.nik_hash_key', config('app.key'))` (disarankan kunci khusus, jangan diganti setelah ada data).
- **Telepon:** normalisasi lewat `App\Support\Telepon::normal()` ke `62812…` sebelum disimpan/dicocokkan.
- **Penomoran dokumen:** `App\Services\PenomoranDokumen::berikutnya($kode, $cabangId, $tahun, $polaBawaan)`; token `{SEQ:n}`, `{TAHUN}`, `{BULAN}`, `{BULAN_ROMAWI}`, `{CABANG}`; baris `penomoran` dikunci.
- **JANGAN menangkap `UniqueConstraintViolationException` di dalam transaksi PostgreSQL** (transaksi menjadi aborted). Gunakan pemeriksaan awal, `firstOrCreate`, atau savepoint.
- **JANGAN** memakai `ShouldDispatchAfterCommit` pada event yang diuji dengan `RefreshDatabase` (callback after-commit tidak jalan di transaksi tes). Event di proyek ini di-dispatch **eksplisit setelah** transaksi service selesai.
- Pesan galat/validasi untuk pengguna akhir: **bahasa Indonesia**.

---

## 3. Setup dan lingkungan

### 3.1 Registrasi manual yang harus ada di repo pengguna

| Hal | Lokasi |
|---|---|
| Provider `App\Providers\AksesServiceProvider` (mendaftarkan `CabangKonteks` scoped, `Relation::morphMap(['user','orang'])`, `Gate::before` sysadmin) | `bootstrap/providers.php` |
| `require __DIR__.'/spmb.php';` dan `require __DIR__.'/spmb_admin.php';` | `routes/web.php` |
| `Schedule::command('pesan:kirim')->everyMinute()->withoutOverlapping();` + cron `schedule:run` | `routes/console.php` |
| Migrasi `users` bawaan Laravel (`0001_01_01_000000_create_users_table.php`) **dihapus** (digantikan migrasi proyek) | `database/migrations` |
| `App\Models\User` pengganti bawaan (FilamentUser, HasName, HasRoles, `$attributes = ['is_aktif' => true]`) | `app/Models/User.php` |
| Spatie permission: `config/permission.php` → `'teams' => true`, `'team_foreign_key' => 'cabang_id'` | `config/permission.php` |
| Panel Filament menemukan resource/widget di `app/Filament/` (bawaan `discoverResources/discoverWidgets`) | `app/Providers/Filament/*PanelProvider.php` |

Tambahkan model baru ke `Relation::morphMap` di `AksesServiceProvider` saat dipakai di kolom polimorfik (`subjek_type`, `lampiran_type`, `konteks_type`, `model_type` spatie). `enforceMorphMap` belum diaktifkan.

### 3.2 Variabel lingkungan / konfigurasi

```
APP_TIMEZONE=Asia/Jakarta        # jam tenang pesan memakai zona ini
APP_URL=https://...               # tautan di dalam pesan dibuat dari sini
SYSADMIN_USERNAME= SYSADMIN_PASSWORD(min 12) SYSADMIN_NAMA= SYSADMIN_JENIS_KELAMIN=L|P SYSADMIN_EMAIL=
PESAN_AKTIF=true
PESAN_PENGIRIM_WHATSAPP=log       # ganti saat penyedia dipilih
PESAN_PENGIRIM_EMAIL=surel
NIK_HASH_KEY=                     # butuh entri 'nik_hash_key' => env('NIK_HASH_KEY') di config/app.php agar terbaca
```

File config proyek: `config/akses.php` (peran_panel), `config/spmb.php` (berkas_maks_kb=3072, tampilkan_hasil_di_status=false, sumber_bawaan='website', peran_panitia), `config/pesan.php` (aktif, pengirim, driver, maks_percobaan, backoff_menit, batas_per_proses, jeda_ms, jam_tenang 21:00–06:00, proses_macet_menit, peran_lihat, peran_templat), `config/sysadmin.php`.

### 3.3 Menjalankan

```bash
php artisan migrate
php artisan db:seed                                   # Jenjang, Tingkat, Referensi, Peran, AkunSysadmin (dilewati bila SYSADMIN_* kosong)
php artisan db:seed --class=TemplatPesanSeeder        # BELUM ada di DatabaseSeeder (lihat TODO)
php artisan test
```

### 3.4 Pengujian (PENTING)

- Tes **harus** berjalan di PostgreSQL, **bukan** SQLite bawaan. `phpunit.xml`: `DB_CONNECTION=pgsql`, `DB_DATABASE=<db khusus tes>` (pengguna memakai `sifa_test` di `127.0.0.1:5432`). `RefreshDatabase` menjalankan `migrate:fresh`: **jangan arahkan ke DB development**.
- Role akun PostgreSQL harus boleh `CREATE EXTENSION btree_gist, pg_trgm`.
- Trait uji `tests/Concerns/MenyiapkanSpmb` (`seedDasar()`, `periodeTerbuka($ubah, $kodeCabang)`, `dataPendaftaran($s, $ubah)`) dipakai bersama. `tests/Support/PengirimUji` = pengirim palsu untuk tes pesan.
- Pola tes panel: `Filament::setCurrentPanel(Filament::getDefaultPanel())`, `actingAs($user)->get(Resource::getUrl('index'))`.
- **Catatan lama yang sudah dibereskan:** `SpmbTest::pendaftar()` harus memakai nama unik (`'Calon Siswa '.Str::random(6)`) karena ada unique index nama+tanggal lahir per periode.

---

## 4. Skema database yang SUDAH menjadi migrasi

Notasi: `(u)` unik, `?` nullable, `→` FK, `(s)` soft delete, `(j)` jsonb. Semua tabel punya `id`, `created_at`, `updated_at` kecuali ditulis lain.

### 4.1 Urutan migrasi (berkas di `database/migrations/`)

```
2026_11_02_000100 aktifkan_ekstensi_postgresql        (btree_gist, pg_trgm)
2026_11_02_000200 buat_tabel_wilayah
2026_11_02_000300 buat_tabel_referensi
2026_11_02_000400 buat_tabel_organisasi               (cabang, jenjang, sekolah, tingkat, unit_kerja)
2026_11_02_000500 buat_tabel_tahun_ajaran             (tahun_ajaran, semester)
2026_11_02_000600 buat_tabel_orang                    (orang, orang_alamat, orang_kontak_darurat)
2026_11_02_000700 buat_tabel_users                    (users, password_reset_tokens, sessions)
2026_11_02_000800 buat_tabel_pengaturan_penomoran
2026_11_02_000900 buat_tabel_layanan_lintas_modul     (approval_*, lampiran, komentar)
2026_11_02_001000 buat_tabel_siswa                    (siswa, siswa_sekolah, siswa_wali)
2026_11_02_001100 buat_tabel_pegawai                  (pegawai, jabatan, penugasan_pegawai)
2026_11_02_001200 buat_tabel_rombel                   (rombel, rombel_siswa)
2026_11_05_000100 buat_tabel_spmb_prospek
2026_11_05_000200 buat_tabel_spmb_pendaftaran
2026_11_05_000300 buat_view_spmb
2026_11_06_000100 perbaiki_tabel_spmb_untuk_formulir  (spmb_pendaftar_wali.jenis_kelamin, unique nama+lahir)
2026_11_09_000100 izinkan_siswa_punya_banyak_pendaftar (drop unique spmb_pendaftar.siswa_id; unique parsial (periode_id,siswa_id))
2026_11_12_000100 buat_tabel_pesan                    (templat_pesan, antrean_pesan)
+ migrasi vendor: spatie permission (teams), activitylog, cache/jobs Laravel
```

### 4.2 Core

- `wilayah(kode u, nama, level, parent_kode, kode_pos)` (data belum di-seed)
- `referensi(kategori, kode, label, urutan, is_aktif, meta j)` `UNIQUE(kategori,kode)`. Kategori ter-seed: `agama, status_nikah, pendidikan, pekerjaan, penghasilan, status_kepegawaian, hubungan_keluarga, spmb_sumber, spmb_kanal_kampanye`.
- `cabang(kode u, nama, alamat?, wilayah_id?, telepon?, email?, latitude?, longitude?, tanggal_berdiri?, status)(s)`
- `jenjang(kode u [TK,SD,SMP], nama, urutan)`
- `sekolah(cabang_id, jenjang_id, nama, npsn? u, nss?, …, status)(s)` `UNIQUE(cabang_id,jenjang_id)`, `UNIQUE(id,cabang_id)`
- `tingkat(jenjang_id, kode, nama, urutan)` ter-seed: TK `A,B`; SD `1–6`; SMP `7–9`
- `unit_kerja(parent_id?, cabang_id?, sekolah_id?, kode, nama, tipe, status)` (FK komposit sekolah+cabang; check)
- `tahun_ajaran(kode u, nama, tanggal_mulai, tanggal_selesai, is_aktif)` partial unique satu aktif; `semester(tahun_ajaran_id, nama, urutan, …, is_aktif)` idem
- `orang(nik text enc?, nik_hash u?, nama_lengkap, nama_panggilan?, gelar_*?, jenis_kelamin L|P, tempat_lahir?, tanggal_lahir?, agama_ref?, status_nikah_ref?, golongan_darah?, kewarganegaraan?, telepon?, whatsapp?, email?, foto_path?, status_hidup)(s)` + GIN trigram `nama_lengkap`; `orang_alamat`; `orang_kontak_darurat`
- `users(orang_id u→orang, username u, email? u, password, is_aktif default true, last_login_at?, remember_token)`; `password_reset_tokens`; `sessions`
- `pengaturan(scope, scope_id?, kunci, nilai j)` unique expression `(scope, COALESCE(scope_id,0), kunci)`
- `penomoran(kode, cabang_id?, tahun?, pola, nomor_terakhir)` unique expression `(kode, COALESCE(cabang_id,0), COALESCE(tahun,0))`
- `approval_alur`, `approval_alur_langkah`, `approval_permohonan(morph subjek)`, `approval_aksi`, `approval_delegasi` (**tabel saja, engine approval belum dibuat**)
- `lampiran(morph lampiran, jenis?, disk, path, nama_asli, mime, ukuran, checksum?, teks_ekstrak?, diunggah_oleh?)(s)`; `komentar(morph komentar, user_id, parent_id?, isi)(s)`

### 4.3 Data pokok

- `siswa(orang_id u, nis_yayasan u, nisn? u, anak_ke?, jumlah_saudara?, tanggal_masuk_yayasan, status)(s)`
- `siswa_sekolah(siswa_id, sekolah_id+cabang_id, nis_lokal?, tanggal_masuk, jenis_masuk[baru|pindahan|naik_jenjang], asal_sekolah?, tanggal_keluar?, jenis_keluar?)` **exclusion constraint**: periode `[masuk,keluar)` per siswa tidak boleh tumpang tindih (`btree_gist`)
- `siswa_wali(siswa_id, orang_id, hubungan[ayah|ibu|wali], pekerjaan_ref?, pendidikan_ref?, penghasilan_ref?, is_kontak_utama, is_penanggung_biaya, is_serumah)` `UNIQUE(siswa_id,orang_id)`
- `pegawai(orang_id u, nip_yayasan u, tipe, status_kepegawaian_ref, tanggal_masuk, tanggal_keluar?, alasan_keluar?, status)(s)`; `jabatan(kode u, nama, kategori, unit_kerja_id?, is_aktif)`; `penugasan_pegawai(pegawai_id, cabang_id, sekolah_id?, unit_kerja_id?, jabatan_id, tmt, tanggal_akhir?, is_utama, no_sk?, status)` partial unique: satu penugasan utama berjalan per pegawai
- `rombel(sekolah_id+cabang_id, tahun_ajaran_id, tingkat_id, nama, wali_kelas_id?→pegawai, kapasitas?, status)` `UNIQUE(sekolah_id,tahun_ajaran_id,nama)`, `UNIQUE(id,cabang_id,tahun_ajaran_id)`
- `rombel_siswa(rombel_id+cabang_id+tahun_ajaran_id, siswa_id, no_absen?, tanggal_masuk, tanggal_keluar?, status)` `UNIQUE(tahun_ajaran_id,siswa_id)`

### 4.4 SPMB

- `spmb_periode(cabang_id+sekolah_id, tahun_ajaran_id, nama, gelombang, tanggal_buka, tanggal_tutup, kuota, biaya_pendaftaran, status[draft|dibuka|ditutup|selesai])` `UNIQUE(sekolah_id,tahun_ajaran_id,gelombang)`, `UNIQUE(id,cabang_id,sekolah_id)`
- `spmb_jalur(periode_id, nama, kuota?, persyaratan? j, is_aktif)` `UNIQUE(periode_id,nama)`, `UNIQUE(id,periode_id)`
- `spmb_persyaratan_berkas(periode_id, nama, is_wajib, urutan)` `UNIQUE(id,periode_id)`
- `spmb_kampanye(cabang_id?, nama, kanal_ref, tanggal_mulai, tanggal_akhir?, anggaran?, catatan?)`
- `spmb_kontak(cabang_id, sekolah_id?, tahun_ajaran_id, nama_kontak, telepon [dinormalisasi], whatsapp?, email?, nama_calon_siswa?, jenjang_minat_id?, sumber_ref, kampanye_id?, perujuk_id?→orang, petugas_id?→pegawai, status[baru|dihubungi|tertarik|mendaftar|tidak_lanjut], alasan_tidak_lanjut?, kontak_pertama_pada, catatan?)` unique expression `(cabang_id, tahun_ajaran_id, telepon, LOWER(COALESCE(nama_calon_siswa,'')))`
- `spmb_tindak_lanjut`, `spmb_kegiatan`, `spmb_kegiatan_peserta` (tabel saja; belum ada UI)
- `spmb_pendaftar(periode_id, jalur_id, cabang_id, sekolah_id, kontak_id?, nomor_pendaftaran u, tanggal_daftar, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, nik enc?, nik_hash?, nisn?, agama_ref?, alamat?, wilayah_id?, asal_sekolah?, status, siswa_id?)` — constraint: `UNIQUE(periode_id,nik_hash)`, unique index `(periode_id, LOWER(nama_lengkap), tanggal_lahir)`, unique parsial `(periode_id,siswa_id) WHERE siswa_id IS NOT NULL`, `UNIQUE(id,periode_id)`, FK komposit `(periode_id,cabang_id,sekolah_id)` dan `(jalur_id,periode_id)`, GIN trigram nama
- `spmb_pendaftar_wali(pendaftar_id, hubungan, nama, jenis_kelamin?, nik enc?, telepon?, pekerjaan_ref?, penghasilan_ref?, is_kontak_utama)`
- `spmb_berkas(pendaftar_id, persyaratan_id, periode_id, status_verifikasi[menunggu|valid|tidak_valid], catatan?, diverifikasi_oleh?→users)` `UNIQUE(pendaftar_id,persyaratan_id)`; file lewat `lampiran` (morph), disk `local` (privat)
- `spmb_tahap_seleksi(periode_id, nama, urutan, bobot, nilai_lulus?, ujian_id? [FK belum dipasang; menunggu tabel CBT F4])`, `spmb_nilai_seleksi(pendaftar_id, tahap_id, periode_id, tanggal, nilai?, penguji_id?, catatan?)` `UNIQUE(pendaftar_id,tahap_id)`
- `spmb_hasil(pendaftar_id u, skor_total?, peringkat?, keputusan[lulus|cadangan|tidak_lulus], tanggal_keputusan, ditetapkan_oleh→users, catatan?)`
- `spmb_daftar_ulang(pendaftar_id u, tanggal?, status[menunggu|selesai|batal], rombel_id?)`
- View: `v_spmb_corong`, `v_spmb_kinerja_petugas`, `v_spmb_roi_kampanye` (belum dipakai UI)

### 4.5 Pesan

- `templat_pesan(kode, kanal, judul?, isi, variabel j, is_aktif)` `UNIQUE(kode,kanal)`. Templat ter-seed: `spmb_terdaftar`, `spmb_berkas_ditolak`, `spmb_terverifikasi` (kanal `whatsapp`); sintaks variabel `{{nama_variabel}}`.
- `antrean_pesan(cabang_id?, kanal, tujuan, templat_id?, judul?, isi, status[antri|diproses|terkirim|gagal|dibatalkan] (check), percobaan, dijadwalkan_pada, diproses_pada?, dikirim_pada?, respons? j, galat?, kunci_unik? u, konteks morph?)`

---

## 5. Model dan service utama

### 5.1 Model (`app/Models`, semua `extends BaseModel`)

`BaseModel`, `User` (bukan BaseModel; Authenticatable), `Orang`, `Cabang`, `Sekolah`, `TahunAjaran`, `Rombel`, `RombelSiswa`, `Siswa`, `SiswaSekolah`, `SiswaWali`, `Lampiran`, `SpmbPeriode`, `SpmbJalur`, `SpmbPersyaratanBerkas`, `SpmbKontak`, `SpmbPendaftar`, `SpmbPendaftarWali` (nik terenkripsi), `SpmbBerkas` (morphMany `lampiran`), `SpmbTahapSeleksi`, `SpmbNilaiSeleksi`, `SpmbHasil`, `SpmbDaftarUlang`, `TemplatPesan`, `AntreanPesan`. Concerns: `BerLingkupCabang`, `MemilikiNikTerenkripsi`. Scope: `LingkupCabang`.

Model **belum ada** untuk: `Pegawai`, `Jabatan`, `PenugasanPegawai`, `UnitKerja`, `Semester`, `Referensi`, `Wilayah`, tabel approval/pengaturan/penomoran (diakses via `DB::table`). Buat saat dibutuhkan.

### 5.2 Service SPMB (`app/Services/Spmb`)

- `PendaftaranSpmb::daftar(SpmbPeriode, array $data, array $berkas): SpmbPendaftar` (transaksi: cek buka/tutup → tolak ganda (NIK atau nama+tgl lahir) → cari/buat `spmb_kontak` (nomor kontak utama + nama anak) → buat pendaftar (status `terdaftar`, nomor otomatis `SPMB/{CABANG}/{TAHUN}/{SEQ:4}`) → wali → berkas+lampiran) lalu dispatch `PendaftarTerdaftar`. Juga `terbuka(periode)`.
- `AlurSeleksiSpmb`: `verifikasi` (syarat semua berkas wajib `valid`; `terdaftar→diverifikasi`; dispatch `PendaftarTerverifikasi`), `tetapkanBerkas(berkas, status, catatan, userId)` (alasan wajib bila `tidak_valid`; dispatch `BerkasDitolak`), `catatNilai` (upsert per tahap; `diverifikasi→seleksi`), `hitungSkor` (rata-rata berbobot), `tetapkanHasil` (`lulus|cadangan|tidak_lulus`), `hitungPeringkat(periodeId)` (SQL `RANK()`), `mulaiDaftarUlang` (`lulus→daftar_ulang`), `selesaikanDaftarUlang(p, ?rombelId, ?tanggal)`, `mundur(p, ?alasan)`. Galat: `TransisiTidakValid`.
- `KonversiPendaftar::konversi(p, ?rombelId, ?tanggalMasuk): Siswa` (syarat: hasil `lulus` + daftar ulang `selesai` + belum dikonversi; `orang`/`siswa`/`siswa_sekolah`/`siswa_wali`/`rombel_siswa`; **memakai ulang `orang` berdasarkan NIK** (wali kakak-adik, anak naik jenjang → `jenis_masuk='naik_jenjang'` dan siswa yang sama; ditolak bila keanggotaan lama belum punya `tanggal_keluar`); NIS = `PenomoranDokumen('nis_yayasan', null, tahun, '{TAHUN}{SEQ:4}')`; `p.status='menjadi_siswa'`). Galat: `KonversiTidakBisa`.
- `PerbaikanBerkasSpmb::ganti(berkas, file)` (hanya berkas `tidak_valid`, pendaftar berstatus `draft|terdaftar`; lampiran lama di-soft-delete; status kembali `menunggu`). Galat: `UnggahUlangDitolak`.
- `NotifikasiSpmb` (terdaftar, terverifikasi, berkasDitolak → `PengelolaPesan::antri` ke **kontak utama**; kunci unik per peristiwa).

**State machine status pendaftar:** `draft → terdaftar → diverifikasi → seleksi → (lulus | cadangan | tidak_lulus)`; `lulus → daftar_ulang → menjadi_siswa` (lewat konversi); `mundur` dari status mana pun sebelum `menjadi_siswa`.

### 5.3 Pesan (`app/Pesan`, `app/Services/Pesan`)

- Kontrak `Pengirim::kirim(string $tujuan, string $isi, ?string $judul): HasilKirim`; `HasilKirim::sukses()/gagal($galat, $bisaDiulang)`; driver `LogPengirim` (default; **tidak mengirim apa pun**), `EmailPengirim`. Driver didaftarkan di `config/pesan.php → driver`, dipilih per kanal di `pengirim`.
- `PengelolaPesan::antri($kode, $tujuan, $data, $opsi)` (templat → render `{{var}}` → normalisasi nomor → jam tenang → `firstOrCreate` by `kunci_unik`; mengembalikan `null` tanpa melempar bila pesan nonaktif/templat tak ada), `antriLangsung`, `render`, `pengirim($kanal)`.
- Perintah `pesan:kirim` (tiap menit; ambil `FOR UPDATE SKIP LOCKED`, tandai `diproses`, kirim, backoff 1/5/15/60/180 menit, maks 5 percobaan; pesan `diproses` > 10 menit dipulihkan), `pesan:uji {tujuan}`. `JamTenang::sesuaikan()` mengundur ke 06:00.

---

## 6. Fitur yang sudah selesai (menurut paket yang diserahkan)

Legenda: ✅ dipasang & tes lolos (dilaporkan pengguna) · ⚠️ diserahkan, **belum dikonfirmasi pengguna**

| Fitur | Status |
|---|---|
| Migrasi Core + Data Pokok, seeder jenjang/tingkat/referensi | ✅ |
| Peran (12), `PenugasanPeran`, `AkunSysadminSeeder`, `Gate::before` sysadmin | ✅ |
| Penomoran dokumen, trait NIK terenkripsi, normalisasi telepon | ✅ |
| Migrasi SPMB (prospek, pendaftaran, view) + perbaikan (wali.jenis_kelamin, anti-ganda, siswa_id) | ✅ |
| Formulir pendaftaran publik (`/spmb`, `/spmb/{periode}`), halaman nomor pendaftaran, **cek status** (`/spmb/status`) | ✅ |
| Konversi pendaftar → siswa + perintah `spmb:konversi`, `spmb:ekspor` (CSV tanpa NIK) | ✅ |
| Panel admin Filament: Periode SPMB (+ relation manager jalur/persyaratan/tahap), Pendaftar (daftar, detail, koreksi biodata, relation manager berkas+nilai, tindakan: verifikasi, tetapkan hasil, mulai/selesai daftar ulang, jadikan siswa, mundur), widget ringkasan, hitung peringkat | ✅ (tes HTTP + Livewire: `Umumkan hasil`, `Tetapkan hasil`, `Daftar ulang selesai`, `Verifikasi`, `Koreksi biodata`, `Kirim ulang`, `Batalkan`) |
| CRM Prospek: Kontak (+ relation manager tindak lanjut), Kampanye, Kegiatan, widget CorongSpmb | ✅ (tes HTTP 3 resource) |
| Arsip: Surat Masuk, Surat Keluar (dengan penomoran otomatis), Dokumen (model), Disposisi (model), Klasifikasi (model) | ✅ (tes HTTP 2 resource + tes penomoran) |
| Lingkup cabang berbasis pengguna (`CabangKonteks` fail-closed) + akses panel per peran + unduh berkas privat `/berkas-spmb/{lampiran}` | ✅ |
| Unggah ulang berkas yang ditolak oleh orang tua (`/spmb/status/saya`, `POST /spmb/berkas/{berkas}`, sesi 30 menit) | ✅ diverifikasi, 10 tes lolos |
| Antrean pesan + templat + pengirim log/email + notifikasi otomatis SPMB (terdaftar, berkas ditolak, terverifikasi) + resource Filament Pesan (Antrean, Templat) | ✅ diverifikasi, 41 tes lolos |
| Pengumuman hasil SPMB per periode (service, migrasi, templat, action Filament, tes) | ✅ diverifikasi, 8 tes lolos + 1 tes panel |

Rute publik: `GET /spmb`, `GET /spmb/{id}` (form), `POST /spmb/{id}` (throttle 10/mnt, honeypot `situs`, CSRF), `GET /spmb/terdaftar`, `GET|POST /spmb/status`, `GET /spmb/status/saya`, `POST /spmb/berkas/{berkas}`. Rute staf: `GET /berkas-spmb/{lampiran}` (403 bila bukan panitia di cabang yang berhak). Panel: resource slug `spmb/periode`, `spmb/pendaftar`, `pesan/antrean`, `pesan/templat`.

---

## 7. Struktur berkas yang diharapkan (hasil semua paket)

```
app/
  Console/Commands/{KonversiSpmb,EksporPendaftarSpmb,KirimPesan,UjiPesan}.php
  Enums/Peran.php
  Events/Spmb/{PendaftarTerdaftar,PendaftarTerverifikasi,BerkasDitolak}.php
  Exceptions/Spmb/{PendaftaranDitutup,PendaftarGanda,KonversiTidakBisa,TransisiTidakValid,UnggahUlangDitolak}.php
  Filament/
    Concerns/{AksesPanitiaSpmb,AksesPesan}.php
    Resources/Spmb/{SpmbPeriodeResource,SpmbPendaftarResource}.php
    Resources/Spmb/Pages/{ListSpmbPeriode,CreateSpmbPeriode,EditSpmbPeriode,ListSpmbPendaftar,ViewSpmbPendaftar,EditSpmbPendaftar}.php
    Resources/Spmb/RelationManagers/{Jalur,Persyaratan,Tahap,Berkas,Nilai}RelationManager.php
    Resources/Pesan/{AntreanPesanResource,TemplatPesanResource}.php + Pages/
    Widgets/RingkasanSpmb.php
  Http/Controllers/Spmb/{PendaftaranSpmbController,BerkasSpmbController}.php
  Http/Middleware/TetapkanKonteksCabang.php        # opsional sejak CabangKonteks diturunkan dari pengguna
  Http/Requests/DaftarSpmbRequest.php
  Listeners/Spmb/AntrekanNotifikasiSpmb.php
  Models/ ... (lihat 5.1)  Models/Concerns/ Models/Scopes/LingkupCabang.php
  Pesan/{Pengirim,HasilKirim,LogPengirim,EmailPengirim,JamTenang}.php
  Providers/AksesServiceProvider.php
  Services/{PenugasanPeran,PenomoranDokumen}.php
  Services/Pesan/PengelolaPesan.php
  Services/Spmb/{PendaftaranSpmb,AlurSeleksiSpmb,KonversiPendaftar,PerbaikanBerkasSpmb,NotifikasiSpmb}.php
  Support/{CabangKonteks,Telepon,StatusSpmb}.php
config/{akses,spmb,pesan,sysadmin}.php
database/seeders/{DatabaseSeeder,JenjangSeeder,TingkatSeeder,ReferensiSeeder,PeranSeeder,AkunSysadminSeeder,TemplatPesanSeeder}.php
resources/views/spmb/{layout,index,form,tutup,terdaftar,status}.blade.php
routes/{spmb,spmb_admin}.php
tests/Concerns/MenyiapkanSpmb.php  tests/Support/PengirimUji.php
tests/Feature/{PeranTest,SysadminTest,NikTest,PenomoranTest,SpmbTest,PendaftaranSpmbTest,KonversiPendaftarTest,
               AlurSeleksiSpmbTest,LingkupCabangTest,PanelSpmbTest,PerbaikanBerkasSpmbTest,
               PengelolaPesanTest,KirimPesanTest,NotifikasiSpmbTest,PanelPesanTest}.php
```

**Urutan paket zip yang pernah diserahkan** (yang belakangan menimpa yang lama; bila ada konflik, versi **belakangan** benar): `migrasi-f0` → `migrasi-f1-spmb` → `spmb-formulir-konversi` (+ migrasi `2026_11_09_000100` terpisah) → `spmb-admin-filament` → `spmb-unggah-ulang` → `antrean-pesan`. Berkas yang ditimpa lintas paket: `Orang.php` (trait NIK), `ReferensiSeeder`, `CabangKonteks`, `PenugasanPeran`, `User.php`, `config/spmb.php`, `SpmbPendaftar/Periode/Berkas` (relasi), `PendaftaranSpmbController`, `routes/spmb.php`, `status.blade.php`, `RingkasanSpmb`, `BerkasRelationManager`, `PendaftaranSpmb`, `AlurSeleksiSpmb`, `MenyiapkanSpmb`.

---

## 8. Keputusan produk yang sudah disepakati tetapi BELUM diimplementasi

(Detail tabel ada di dokumen skema v0.2; jangan mengubah arah tanpa konfirmasi pengguna.)

- **Akuntansi dibangun sendiri, multi-entitas:** `entitas_akuntansi` (pohon yayasan → cabang → sekolah), satu bagan akun standar yayasan + akun lokal, jurnal/periode per entitas, `transaksi_antar_entitas` + eliminasi konsolidasi, `aturan_alokasi_biaya`; tabel transaksi Keuangan membawa `entitas_id` di samping `cabang_id`.
- **Rapor fleksibel:** `rapor_templat` + `rapor_templat_bagian` + `rapor_butir` generik, `skala_penilaian(_butir)`, `aturan_olah_nilai`; **penilaian deskriptif TK** terpisah (`tk_aspek`, `tk_indikator`, `tk_tema`, `tk_observasi`, `tk_ceklis_capaian`, `tk_hasil_karya`, `tk_asesmen_semester`, `tk_refleksi_orang_tua`).
- **Bank soal/CBT ala ExamView + bentuk soal ANBK/TKA** (PG, PG kompleks pilih & kategori, menjodohkan, isian singkat, isian angka, uraian); konten soal sebagai blok JSON (TipTap/ProseMirror, formula LaTeX/KaTeX, gambar dengan layout), paket soal berversi A–Z dengan acak urutan, ekspor **PDF (HTML→Chromium/Gotenberg)** dan **DOCX (Pandoc, nomor soal teracak, kunci per versi)**.
- LMS dan CBT dibangun sendiri (bukan Moodle). Kepegawaian: absensi, cuti, KPI, payroll (komponen_gaji + formula), pelatihan. Inventaris: tanah/bangunan/ruang, aset unit, stok, peminjaman, mutasi, penyusutan. Arsip/persuratan: klasifikasi arsip, surat masuk/keluar + disposisi, dokumen berversi, knowledge base. Modul pendukung: perpustakaan, UKS, komunikasi, tiket layanan.
- Notifikasi **WhatsApp**: penyedia **belum diputuskan** (pengguna memilih "bangun antrean pesan dulu"). Opsi yang sudah dijelaskan: WhatsApp Business API resmi (Meta Cloud API; template butuh persetujuan, berbayar) vs gateway pihak ketiga (cepat/murah, tidak resmi, risiko blokir nomor).

---

## 9. Status verifikasi dan risiko terbuka

### 9.1 Yang sudah dijalankan pengguna

- Migrasi F0, seeder, dan semua tes sampai paket admin Filament: **lolos, total 70 tes** (laporan terakhir pengguna). Sepanjang jalan, perbaikan yang sudah diterapkan: tes harus di PostgreSQL (`sifa_test`); unique `spmb_pendaftar.siswa_id` dibuang; `User::$attributes['is_aktif']=true`; helper `SpmbTest::pendaftar()` memakai nama unik.

### 9.2 Diserahkan tetapi BELUM dikonfirmasi (kerjakan verifikasi ini lebih dulu)

1. ~~**`spmb-unggah-ulang.zip`**~~ **SELESAI** — diverifikasi 15 Nov 2026, 10 tes lolos.
2. ~~**`antrean-pesan.zip`**~~ **SELESAI** — diverifikasi 15 Nov 2026, 41 tes lolos (termasuk `PanelPesanTest` yang diperbarui).
3. ~~Risiko spesifik paket pesan~~ **SELESAI** — auto-discovery listener, `FOR UPDATE SKIP LOCKED`, dan jam tenang sudah teruji di PostgreSQL.
4. **Celah cakupan tes (smoke test manual atau tambah tes Livewire):** semua **tindakan modal Filament** (`Tetapkan hasil`, `Daftar ulang selesai` dengan form, `Tidak valid`, `Catat nilai`, `Koreksi biodata`), form create/edit periode, relation manager jalur/persyaratan/tahap, widget `RingkasanSpmb`, resource Pesan (aksi kirim ulang/batalkan), dan tampilan Blade di browser. **Sebagian sudah ditambahkan:** aksi `Umumkan hasil`, `Tetapkan hasil`, `Daftar ulang selesai`, `Verifikasi`, `Koreksi biodata`, `Kirim ulang`, dan `Batalkan` sudah diuji via Livewire. **Masih perlu:** `Tidak valid` (relation manager), `Catat nilai` (relation manager), form create/edit periode, relation manager jalur/persyaratan/tahap, widget `RingkasanSpmb`.
5. `Action::schema()` (v4/v5) dipakai untuk form modal; bila "method not found", ganti `->form([...])`.
6. **Modul Absensi Harian (SDM)** — selesai 15 Nov 2026: migrasi `2026_11_15_055448_buat_tabel_sdm_absensi_harian`, model `AbsensiLogMentah`/`AbsensiPegawai`, service `AbsensiService`/`AbsensiLogProcessor`, resource Filament `AbsensiResource` (list/create/edit + koreksi), dan 7 tes lolos (`AbsensiTest`). Fitur: impor log mentah mesin/GPS/QR, dedup per perangkat, proses log → absensi harian (hadir/terlambat + hitung terlambat_menit/pulang_cepat_menit sesuai shift), catat manual, dan proteksi status izin/sakit/cuti/dinas agar tidak ditimpa log mesin.
7. **Modul Cuti & Izin (SDM)** — selesai 19 Nov 2026: migrasi `2026_11_15_055449_buat_tabel_sdm_cuti_izin` (hari_libur, jenis_cuti, saldo_cuti, mutasi_saldo_cuti, pengajuan_cuti/izin + _hari, FK absensi, `kepala_pegawai_id` pada unit_kerja), model lengkap, service `CutiService`/`IzinService`/`OtorisasiPengajuan`/`MutasiSaldoCutiService`/`PerhitunganCutiIzin`, resource Filament `PengajuanCutiResource`/`PengajuanIzinResource`/`JenisCutiResource`/`SaldoCutiResource`/`HariLiburResource`. Tes lolos PostgreSQL (`sifa_test`): `CutiIzinTest` 20, `PanelSdmTest` 13, `SdmMasterTest` 7. Fitur: pengajuan cuti/izin dengan hitung hari shift+libur, validasi saldo cuti per tahun, persetujuan bertingkat (atasan → final), penolakan dan pembatalan dengan alasan wajib, saldo dipotong saat final, absensi ditimpa dengan sumber tercatat, carry-over tahun berikutnya, penyesuaian saldo manual bermutasi, isolasi cabang. `OtorisasiPersetujuan` lama telah dihapus.
8. **Modul KPI/Penilaian Kinerja (SDM)** — selesai 15 Nov 2026: migrasi `2026_11_15_055450_buat_tabel_sdm_kpi`, model `KpiPeriode`/`KpiIndikator`/`KpiTemplat`/`KpiTemplatIndikator`/`KpiPenilaian`/`KpiPenilaianDetail`, service `KpiService`, dan 6 tes lolos (`KpiTest`). Fitur: buat penilaian dari templat, isi realisasi manual/otomatis (kehadiran), hitung skor berdasarkan bobot dan capaian, finalisasi dengan predikat A/B/C/D, dan persetujuan.
9. **Modul Payroll (SDM)** — selesai 15 Nov 2026: migrasi `2026_11_15_055451_buat_tabel_sdm_payroll`, model `KomponenGaji`/`SkemaGaji`/`SkemaGajiKomponen`/`GajiPegawai`/`GajiPegawaiKomponen`/`PeriodeGaji`/`SlipGaji`/`SlipGajiRincian`, service `PayrollService`, dan 7 tes lolos (`PayrollTest`). Fitur: tetapkan gaji pokok (riwayat), buat periode gaji, hitung gaji dengan komponen tetap/persen/formula/per_kehadiran, snapshot rincian slip, setujui dan bayar periode.

### 9.3 Utang teknis / batasan yang diketahui

- ~~`TemplatPesanSeeder` belum dipanggil dari `DatabaseSeeder`.~~ **SELESAI** — sudah dipanggil sejak 15 Nov 2026.
- ~~Hasil seleksi: penayangan di halaman cek status memakai **satu flag global** `config('spmb.tampilkan_hasil_di_status')`; belum per periode.~~ **SELESAI** — sudah digantikan oleh `spmb_periode.hasil_diumumkan_pada` per periode sejak 15 Nov 2026.
- Tidak ada UI untuk CRM prospek (`spmb_kontak`, tindak lanjut, kampanye), dashboard corong (view ada), aksi massal, atau pemberitahuan ke panitia saat berkas diunggah ulang (hanya angka di widget).
- Persetujuan penggunaan data di formulir wajib dicentang tetapi **tidak disimpan** (belum ada kolom).
- Berkas yatim bisa tertinggal bila transaksi gagal setelah unggah; berkas lama hasil unggah ulang sengaja disimpan (arsip).
- Edit `nik` di panel dapat memicu unique violation bila bentrok (sudah ada validasi closure di form).
- `wilayah` belum di-seed; `pegawai/jabatan` belum ada model/UI; approval engine belum ada; izin (permission) per peran belum ada.
- `NIS yayasan` memakai pola awal `{TAHUN}{SEQ:4}`; **harus disesuaikan** dengan format NIS yang sudah berlaku (edit/baris `penomoran` kode `nis_yayasan`) sebelum konversi pertama di produksi; bentrok dengan NIS lama akan ditolak unique index.

### 9.4 Paket A — Perbaikan integritas dan akses SDM (selesai 15 Nov 2026)

- Form create/edit absensi, cuti, izin tersambung ke layanan bisnis (bukan langsung Eloquent).
- Otorisasi eksplisit pada aksi setujui/tolak cuti/izin (`canApprovePengajuan` di `AksesSdm`).
- Pilihan pegawai di form dibatasi berdasarkan cabang yang diizinkan (`PegawaiResource::getEloquentQuery`).
- 6 tes baru di `PanelSdmTest`: create absensi, create cuti, validasi saldo, admin tidak bisa setujui, kepala cabang bisa setujui/tolak.
- Total tes: 189 (183 baseline + 6 baru).

---

## 10. TUGAS BERIKUTNYA (siap dikerjakan)

### 10.1 Prasyarat

Selesaikan 9.2 (pasang paket, jalankan `php artisan test`, perbaiki galat). Catat hasilnya.

### 10.2 ~~Tugas utama: "Umumkan hasil" per periode SPMB~~ **SELESAI 15 Nov 2026**

Fitur sudah diimplementasikan dan diverifikasi:

- Migrasi `2026_11_14_000100_tambahkan_pengumuman_hasil_spmb` sudah berjalan.
- `PengumumanHasilSpmb` service sudah teruji (8 tes lolos).
- Action `Umumkan hasil` di Filament sudah teruji via Livewire (1 tes lolos).
- Templat pesan `spmb_hasil_lulus`, `spmb_hasil_cadangan`, `spmb_hasil_tidak_lulus` sudah tersedia di `TemplatPesanSeeder`.
- `TemplatPesanSeeder` sudah dipanggil dari `DatabaseSeeder`.
- Halaman cek status menampilkan hasil hanya setelah periode diumumkan (atau bila flag global `true`).
- **Paket 2 tambahan:** tes Livewire untuk `Tetapkan hasil`, `Daftar ulang selesai`, `Verifikasi`, `Koreksi biodata`, `Kirim ulang`, dan `Batalkan` sudah ditambahkan dan lolos.
- Total suite: **132 tes, 355 asersi, hijau** di PostgreSQL `sifa_test`.

### 10.3 ~~Tugas berikutnya~~ **SELESAI 15 Nov 2026**

1. ~~Tes Livewire/Filament untuk tindakan modal lainnya~~ **SELESAI** — aksi `Umumkan hasil`, `Tetapkan hasil`, `Daftar ulang selesai`, `Verifikasi`, `Koreksi biodata`, `Kirim ulang`, dan `Batalkan` sudah diuji.
2. ~~Layar CRM prospek~~ **SELESAI** — resource `spmb_kontak` (dengan relation manager tindak lanjut), `spmb_kampanye`, `spmb_kegiatan`, dan widget `CorongSpmb` sudah dibuat dan diuji (3 tes HTTP).
3. ~~Modul Arsip & persuratan~~ **SELESAI** — migrasi tabel Arsip, model, resource `SuratMasuk`, `SuratKeluar`, dan `Dokumen` sudah dibuat. Tes HTTP 2 resource lolos. **Catatan:** resource `Disposisi`, `KlasifikasiArsip`, `TemplatSurat`, `LokasiArsipFisik`, `Dokumen` belum dibuat UI Filament-nya (model sudah tersedia).
4. ~~Modul Inventaris~~ **SELESAI** — migrasi 26 tabel inventaris (tanah/bangunan/ruang, kategori_aset/barang/aset_unit/aset_kendaraan/penyusutan_aset, gudang/stok_barang/mutasi_stok/penerimaan_barang(_baris)/permintaan_barang(_baris)/pengeluaran_barang(_baris)/stok_opname(_baris), peminjaman(_baris)/reservasi_ruang/mutasi_aset(_baris)/laporan_kerusakan/pemeliharaan_aset/penghapusan_aset), 26 model, service `TransaksiStok`/`PeminjamanAset`/`MutasiAsetService`, dan resource Filament (Barang, AsetUnit, Gudang, Ruang, Peminjaman) sudah dibuat. 5 tes lolos (lingkup cabang gudang+ruang, transaksi stok +/−, stok tidak boleh minus, transfer stok antar gudang). Kolom yang menunjuk `akun`/`periode_akuntansi`/`jurnal` dibiarkan tanpa FK sampai modul Keuangan ada.
5. ~~F2 Keuangan (fondasi akuntansi)~~ **SELESAI** — migrasi 8 tabel keuangan dasar (entitas_akuntansi, akun, pusat_biaya, periode_akuntansi, jurnal, jurnal_baris, saldo_akun_periode, pemetaan_akun), 8 model, service `SinkronisasiEntitas` (membuat entitas yayasan→cabang→sekolah dari organisasi yang sudah ada), `JurnalService` (buat/ubah draft, posting, void dengan jurnal pembalik, tutup periode dengan snapshot saldo), config `keuangan.php` (peran lihat/draft/posting/master/tutup periode), trait `AksesKeuangan`, dan resource Filament (EntitasAkuntansi dengan tombol sinkronisasi, Akun, Jurnal dengan repeater baris debit/kredit) sudah dibuat. 6 tes lolos (sinkronisasi entitas, jurnal draft+posting, tidak seimbang ditolak, periode ditutup ditolak, lingkup cabang, posting ganda ditolak). Kas/bank, tagihan SPMB, dan konsolidasi otomatis ditunda ke paket berikutnya.
6. **F2 Keuangan (Kas & Bank)** **SELESAI 19 Nov 2026** — migrasi 3 tabel (`rekening_kas`, `transaksi_kas`, `rekonsiliasi_bank`), 3 model (`RekeningKas`, `TransaksiKas`, `RekonsiliasiBank`), event `TransaksiKasDicatat`, service `KasService` (masuk/keluar/transfer/batalkan dengan jurnal otomatis via `JurnalService`) dan `RekonsiliasiBankService` (mulai/selesaikan), resource Filament (`RekeningKasResource`, `TransaksiKasResource`, `RekonsiliasiBankResource`) dengan aksi batal dan selesaikan. 8 tes lolos di `KasBankTest` (transaksi masuk/keluar/transfer jurnal seimbang, pembatalan void jurnal, rekening tidak aktif ditolak, transfer ke rekening sama ditolak via constraint, isolasi entitas/cabang, rekonsiliasi hitung selisih). Total suite: 235 tes, 666 asersi, hijau di PostgreSQL `sifa_test`. COA default tidak di-seed; kunci pemetaan diisi manual via panel Akun.
5. **Pengirim WhatsApp nyata** setelah pengguna memutuskan penyedia: satu kelas `Pengirim` + daftar di `config/pesan.php` + pemetaan respons ke `HasilKirim` (permanen vs bisa diulang) + tes dengan `Http::fake`.
6. F2 Keuangan (entitas akuntansi) dan Kepegawaian; F3 Akademik/Kesiswaan; F4 LMS/bank soal/CBT sesuai bagian 8.

---

## 11. Panduan membuat modul/fitur baru (checklist agent)

1. **Baca skema rencana** modulnya di dokumen skema v0.2; cocokkan konvensi (bagian 2.6).
2. **Migrasi:** tabel tunggal Indonesia; FK komposit untuk konsistensi cabang/periode; check + partial unique bila ada aturan; nama index eksplisit bila panjang; `down()` lengkap.
3. **Model:** `extends BaseModel`, `$guarded = []`, `casts()`, relasi dengan FK eksplisit; tambahkan `BerLingkupCabang` bila ada `cabang_id`; daftarkan di `Relation::morphMap` bila dipakai polimorfik.
4. **Service:** semua perubahan status/transaksi di service; lempar exception domain (turunan `RuntimeException`); dispatch event **setelah** transaksi; pesan galat Indonesia.
5. **Filament:** resource dengan trait akses (peran dari config) + `getEloquentQuery` mengikuti global scope (jangan `withoutGlobalScopes()` di resource); aksi memanggil service dan menangkap exception domain → `Notification::danger()`.
6. **Tes:** `RefreshDatabase` di PostgreSQL; gunakan/extend `MenyiapkanSpmb`; uji aturan database (constraint), service, lingkup cabang (admin cabang A vs B), dan akses panel (peran tidak berhak → 403).
7. **Jangan** memakai SQLite, jangan menangkap unique violation di dalam transaksi, jangan `assignRole` langsung, jangan pluralisasi nama tabel, jangan melewati `CabangKonteks` untuk data per cabang.
8. Sertakan README singkat atau perbarui dokumen ini (bagian 6, 9, 10) setiap fitur selesai.

---

## 12. Usulan `.clinerules` (salin ke root repo)

```
# Konteks
Proyek ERP SIT Fathona. Baca HANDOVER.md sebelum bekerja. Pengguna berbahasa Indonesia; jawab dalam bahasa Indonesia.

# Aturan teknis
- Laravel 13 + PostgreSQL 14+ + Filament 5 (API sama dengan v4). Tes selalu di PostgreSQL, bukan SQLite.
- Tabel tunggal berbahasa Indonesia; model extends App\Models\BaseModel; hasMany/hasOne wajib FK eksplisit.
- Data per cabang: trait BerLingkupCabang + App\Support\CabangKonteks; alur publik memakai withoutGlobalScopes().
- Peran lewat App\Services\PenugasanPeran (team 0 = yayasan), bukan assignRole langsung.
- Logika bisnis di app/Services; event dispatch setelah transaksi; jangan tangkap UniqueConstraintViolation di dalam transaksi PostgreSQL.
- Pesan untuk pengguna akhir dalam bahasa Indonesia. Jangan menulis kode tanpa tes; jalankan `php artisan test` sebelum menyatakan selesai.
- Jangan memindahkan ke app/Modules, jangan mengganti stack, jangan menjalankan migrate:fresh di database development.
- Tanyakan ke pengguna bila ada keputusan produk (peran/lingkup, format NIS, penyedia WhatsApp) yang belum tercantum di HANDOVER.md.
```

---

## 13. Glosarium singkat

SPMB = penerimaan murid baru · rombel = rombongan belajar (kelas nyata per tahun ajaran) · tingkat = grade (TK A/B, kelas 1–9) · NIS yayasan/lokal, NISN, NPSN, NIK · CP = calon pelanggan (prospek) · kontak utama = orang tua/wali yang dihubungi sekolah · TMT = tanggal mulai tugas · daftar ulang = konfirmasi pendaftar yang lulus · konversi = mengubah pendaftar menjadi siswa · peran global = peran yang berlaku team 0 (seluruh yayasan).
