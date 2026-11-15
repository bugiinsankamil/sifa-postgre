# Skema Database ERP SIT Fathona

Rancangan v0.2, 2 November 2026. Disusun untuk Laravel dengan pola modular monolith: satu database, kolom cabang\_id sebagai pemisah data antarcabang. Mesin database: PostgreSQL. LMS, CBT, dan akuntansi dibangun sendiri (lihat bagian 15).

## 0. Cara membaca dan konvensi

- Setiap tabel otomatis punya id, created\_at, updated\_at. Tanda (s) menambah deleted\_at (soft delete).
- Kolom wajib diisi kecuali bertanda ?. Kolom xxx\_id adalah FK ke tabel xxx; bila tujuannya lain ditulis xxx\_id->tabel. (u) = unik, unik(a,b) = unik gabungan, (j) = json/jsonb, is\_xxx = boolean.
- Tipe mengikuti nama: tanggal\_xxx = date; xxx\_pada dan waktu\_xxx = timestamp; jam\_xxx = time; nominal, harga, biaya, total, gaji\_xxx = uang decimal(15,2), jangan float; jumlah dan volume = decimal(12,2).
- status dan jenis disimpan sebagai string, daftar nilainya dikunci di PHP enum. Kolom berakhiran \_ref berisi kode dari tabel referensi dengan kategori yang sama dengan nama kolom (agama\_ref, pekerjaan\_ref, dst).
- Relasi polimorfik ditulis xxx\_type + xxx\_id, tanpa FK database. Dipakai untuk lampiran, approval, dan sumber jurnal.
- Aturan modul: FK antarmodul tetap dipasang di database demi integritas, tetapi kode modul A tidak mengquery tabel modul B secara langsung. Gunakan service atau event.
- Tabel yang dipakai global scope cabang memuat cabang\_id eksplisit (denormalisasi disengaja agar tidak perlu join).
- Bahasa: istilah domain berbahasa Indonesia dan tunggal; istilah framework tetap Inggris. Dibanding draf sebelumnya, persons diganti orang dan tgl\_ diganti tanggal\_.
- Fase pengerjaan: F0 Core dan Data Pokok; F1 SPMB, Arsip, Inventaris; F2 Kepegawaian dan Keuangan; F3 Akademik dan Kesiswaan; F4 LMS, Bank Soal, CBT.

## 1. Core (F0)

### 1.1 Organisasi dan waktu akademik

- `cabang`: kode (u), nama, alamat?, wilayah\_id?, telepon?, email?, latitude?, longitude?, tanggal\_berdiri?, status (s)
- `jenjang`: kode (u), nama, urutan. Tabel, bukan enum, agar jenjang baru tidak mengubah kode.
- `sekolah`: cabang\_id, jenjang\_id, nama, npsn? (u), nss?, alamat?, telepon?, email?, tanggal\_operasional?, status; unik(cabang\_id, jenjang\_id) (s). Satu sekolah = satu jenjang di satu cabang.
- `tingkat`: jenjang\_id, kode, nama, urutan; unik(jenjang\_id, kode). Contoh: TK A, TK B, 1 sampai 6, 7 sampai 9.
- `unit_kerja`: parent\_id?->unit\_kerja, cabang\_id?, sekolah\_id?, kode, nama, tipe (yayasan/cabang/divisi/bagian), status. cabang\_id kosong berarti level yayasan.
- `tahun_ajaran`: kode (u), nama, tanggal\_mulai, tanggal\_selesai, is\_aktif. Index unik parsial: hanya satu is\_aktif = true.
- `semester`: tahun\_ajaran\_id, nama, urutan, tanggal\_mulai, tanggal\_selesai, is\_aktif

### 1.2 Pengguna dan akses

- `users`: orang\_id (u), username (u), email? (u), password, is\_aktif, last\_login\_at?, email\_verified\_at?, remember\_token?
- Tabel spatie/laravel-permission (roles, permissions, model\_has\_roles, model\_has\_permissions, role\_has\_permissions) dengan teams = true. team\_id berisi cabang\_id; nilai 0 untuk peran tingkat yayasan.
- `personal_access_tokens` (Sanctum, untuk PWA dan API)

### 1.3 Referensi dan konfigurasi

- `wilayah`: kode (u, kode Kemendagri), nama, level (provinsi/kab\_kota/kecamatan/kelurahan), parent\_kode?, kode\_pos?
- `referensi`: kategori, kode, label, urutan, is\_aktif, meta? (j); unik(kategori, kode). Berisi agama, status\_nikah, pekerjaan, pendidikan, penghasilan, status\_kepegawaian, satuan, bank, dan sejenisnya.
- `pengaturan`: scope (global/cabang/sekolah), scope\_id?, kunci, nilai (j); unik(scope, scope\_id, kunci)
- `penomoran`: kode, cabang\_id?, tahun?, pola, nomor\_terakhir; unik(kode, cabang\_id, tahun). Contoh pola {SEQ:4}/FTN/{BULAN\_ROMAWI}/{TAHUN}. Naikkan nomor dengan lockForUpdate.

### 1.4 Layanan lintas modul

- `approval_alur`: kode (u), nama, modul, cabang\_id?, is\_aktif
- `approval_alur_langkah`: alur\_id, urutan, tipe\_approver (role/jabatan/atasan\_langsung/user), approver\_ref, mode (semua/salah\_satu), nominal\_min?, nominal\_maks?. Batas nominal membuat jenjang approval berbeda menurut besar pengeluaran.
- `approval_permohonan`: alur\_id, subjek\_type, subjek\_id, pemohon\_id->users, status (proses/disetujui/ditolak/dibatalkan), langkah\_saat\_ini?, diajukan\_pada, selesai\_pada?. Index (subjek\_type, subjek\_id).
- `approval_aksi`: permohonan\_id, langkah\_id, user\_id, aksi (setuju/tolak/revisi), catatan?, waktu
- `approval_delegasi`: dari\_user\_id->users, kepada\_user\_id->users, alur\_id?, tanggal\_mulai, tanggal\_selesai
- `lampiran`: lampiran\_type, lampiran\_id, jenis?, disk, path, nama\_asli, mime, ukuran, checksum?, teks\_ekstrak? (hasil OCR/ekstraksi untuk pencarian), diunggah\_oleh->users
- `komentar`: komentar\_type, komentar\_id, user\_id, parent\_id?->komentar, isi
- `activity_log` (spatie/laravel-activitylog), `notifications`, `jobs`, `failed_jobs`, `cache` (bawaan Laravel)

## 2. Data Pokok (F0)

### 2.1 Orang

- `orang`: nik? (u, unik parsial untuk non-null; enkripsi), nama\_lengkap, nama\_panggilan?, gelar\_depan?, gelar\_belakang?, jenis\_kelamin, tempat\_lahir?, tanggal\_lahir?, agama\_ref?, status\_nikah\_ref?, golongan\_darah?, kewarganegaraan?, telepon?, whatsapp?, email?, foto\_path?, status\_hidup (s). Satu-satunya tempat identitas orang: siswa, pegawai, orang tua hanyalah peran.
- `orang_alamat`: orang\_id, jenis (ktp/domisili), alamat, rt?, rw?, wilayah\_id?, kode\_pos?, is\_utama
- `orang_kontak_darurat`: orang\_id, nama, hubungan\_ref, telepon

### 2.2 Siswa

- `siswa`: orang\_id (u), nis\_yayasan (u), nisn? (u), anak\_ke?, jumlah\_saudara?, tanggal\_masuk\_yayasan, status (aktif/lulus/pindah/keluar/cuti) (s)
- `siswa_sekolah`: siswa\_id, sekolah\_id, cabang\_id, nis\_lokal?, tanggal\_masuk, jenis\_masuk (baru/pindahan/naik\_jenjang), asal\_sekolah?, tanggal\_keluar?, jenis\_keluar? (lulus/pindah/keluar). Riwayat keanggotaan: mendukung naik jenjang dan pindah antarcabang tanpa data baru.
- `siswa_wali`: siswa\_id, orang\_id, hubungan (ayah/ibu/wali), pekerjaan\_ref?, pendidikan\_ref?, penghasilan\_ref?, is\_kontak\_utama, is\_penanggung\_biaya, is\_serumah; unik(siswa\_id, orang\_id)

### 2.3 Pegawai dan penugasan

- `pegawai`: orang\_id (u), nip\_yayasan (u), tipe (guru/tendik/staf/pimpinan), status\_kepegawaian\_ref, tanggal\_masuk, tanggal\_keluar?, alasan\_keluar?, status (aktif/cuti/nonaktif/resign) (s)
- `jabatan`: kode (u), nama, kategori (struktural/fungsional/pendukung), unit\_kerja\_id?, is\_aktif
- `penugasan_pegawai`: pegawai\_id, cabang\_id, sekolah\_id?, unit\_kerja\_id?, jabatan\_id, tmt, tanggal\_akhir?, is\_utama, no\_sk?, status. Satu pegawai boleh punya beberapa penugasan aktif.

### 2.4 Rombel

- `rombel`: sekolah\_id, cabang\_id, tahun\_ajaran\_id, tingkat\_id, nama, wali\_kelas\_id?->pegawai, kapasitas?, status; unik(sekolah\_id, tahun\_ajaran\_id, nama). Kelas nyata pada satu tahun ajaran; tingkat dan rombel sengaja dipisah agar riwayat naik kelas terlacak.
- `rombel_siswa`: rombel\_id, siswa\_id, cabang\_id, tahun\_ajaran\_id, no\_absen?, tanggal\_masuk, tanggal\_keluar?, status; unik(tahun\_ajaran\_id, siswa\_id)

## 3. Kepegawaian / SDM (F2)

Identitas, status, dan penugasan pegawai ada di Data Pokok. Modul ini menambah semua yang berkaitan dengan karier, kehadiran, kinerja, dan gaji.

### 3.1 Data pegawai tambahan

- `pegawai_pendidikan`: pegawai\_id, jenjang\_pendidikan\_ref, institusi, jurusan?, tahun\_lulus?, ipk?
- `pegawai_keluarga`: pegawai\_id, nama, hubungan\_ref, tanggal\_lahir?, is\_tanggungan
- `pegawai_pajak_bpjs`: pegawai\_id (u), npwp? (enkripsi), status\_ptkp?, no\_bpjs\_kesehatan?, no\_bpjs\_tk?
- `rekening_pegawai`: pegawai\_id, bank\_ref, nomor\_rekening (enkripsi), atas\_nama, is\_utama
- `pegawai_riwayat_status`: pegawai\_id, status\_kepegawaian\_ref, tmt, no\_sk?, keterangan?
- `kontrak_kerja`: pegawai\_id, nomor (u), jenis (pkwt/tetap/honor), tanggal\_mulai, tanggal\_selesai?, gaji\_pokok?, status. Scheduler memberi peringatan kontrak yang akan berakhir.
- `pegawai_sertifikasi`: pegawai\_id, jenis\_ref, nomor?, penerbit?, tanggal\_terbit?, tanggal\_kedaluwarsa?

### 3.2 Absensi

- `lokasi_absensi`: cabang\_id, nama, latitude, longitude, radius\_meter. Geofence untuk absensi GPS di PWA.
- `perangkat_absensi`: cabang\_id, nama, tipe (fingerprint/wajah/qr), nomor\_seri (u), alamat\_ip?, status
- `shift_kerja`: cabang\_id, nama, jam\_masuk, jam\_pulang, toleransi\_menit, hari\_kerja (j)
- `pegawai_shift`: pegawai\_id, shift\_id, tanggal\_mulai, tanggal\_akhir?
- `absensi_log_mentah`: perangkat\_id?, pegawai\_id?, pin?, waktu, metode, payload? (j). Data apa adanya dari mesin; diolah job menjadi absensi\_pegawai.
- `absensi_pegawai`: pegawai\_id, cabang\_id, tanggal, shift\_id?, jam\_masuk?, jam\_pulang?, status (hadir/terlambat/izin/sakit/cuti/dinas/alpa), metode, latitude?, longitude?, foto\_path?, terlambat\_menit, pulang\_cepat\_menit, keterangan?; unik(pegawai\_id, tanggal)

### 3.3 Cuti, izin, lembur, penugasan khusus

- `jenis_cuti`: kode (u), nama, kuota\_hari\_tahunan?, is\_dibayar, perlu\_lampiran, aturan? (j)
- `saldo_cuti`: pegawai\_id, jenis\_cuti\_id, tahun, kuota, terpakai; unik(pegawai\_id, jenis\_cuti\_id, tahun)
- `pengajuan_cuti`: pegawai\_id, cabang\_id, jenis\_cuti\_id, tanggal\_mulai, tanggal\_selesai, jumlah\_hari, alasan?, pengganti\_id?->pegawai, status. Persetujuan lewat approval\_permohonan (subjek = pengajuan\_cuti).
- `pengajuan_izin`: pegawai\_id, cabang\_id, jenis (izin/sakit/dinas\_luar), tanggal\_mulai, tanggal\_selesai, alasan?, status
- `lembur`: pegawai\_id, cabang\_id, tanggal, jam\_mulai, jam\_selesai, durasi\_menit, alasan, status
- `penugasan_khusus`: cabang\_id, nomor\_surat?, judul, uraian?, tanggal\_mulai, tanggal\_selesai?, lokasi?, status
- `penugasan_khusus_pegawai`: penugasan\_khusus\_id, pegawai\_id, peran?

### 3.4 Kinerja berbasis KPI

- `kpi_periode`: nama, tanggal\_mulai, tanggal\_selesai, cabang\_id?, status (draft/berjalan/ditutup)
- `kpi_indikator`: kode (u), nama, deskripsi?, satuan?, arah (semakin\_tinggi/semakin\_rendah), sumber\_data (manual/otomatis), kunci\_otomatis? (misalnya kehadiran\_persen), is\_aktif
- `kpi_templat`: nama, tipe\_pegawai?, jabatan\_id?, is\_aktif
- `kpi_templat_indikator`: templat\_id, indikator\_id, bobot, target
- `kpi_penilaian`: periode\_id, pegawai\_id, penilai\_id->pegawai, templat\_id, skor\_akhir?, predikat?, status (draft/dinilai/disetujui), catatan?
- `kpi_penilaian_detail`: penilaian\_id, indikator\_id, bobot, target, realisasi?, skor?, catatan?

Indikator bersumber otomatis (kehadiran, ketepatan jurnal mengajar, penyelesaian modul ajar) diisi job dari modul lain lewat event, bukan query langsung.

### 3.5 Pelatihan

- `pelatihan`: cabang\_id?, judul, jenis\_ref, penyelenggara?, tanggal\_mulai, tanggal\_selesai, lokasi?, biaya?, status
- `pelatihan_peserta`: pelatihan\_id, pegawai\_id, status (terdaftar/hadir/lulus/tidak\_hadir), nilai?, nomor\_sertifikat?

### 3.6 Payroll

- `komponen_gaji`: kode (u), nama, tipe (pendapatan/potongan), cara\_hitung (tetap/persen/formula/per\_kehadiran), formula? (j), kena\_pajak, kena\_bpjs, urutan, is\_aktif
- `skema_gaji`: nama, deskripsi?, is\_aktif
- `skema_gaji_komponen`: skema\_id, komponen\_id, nominal?, persen?
- `gaji_pegawai`: pegawai\_id, skema\_id?, gaji\_pokok, tanggal\_berlaku, no\_sk?, status. Riwayat gaji: baris baru setiap perubahan, tidak menimpa.
- `gaji_pegawai_komponen`: gaji\_pegawai\_id, komponen\_id, nominal
- `periode_gaji`: cabang\_id, tahun, bulan, tanggal\_mulai, tanggal\_selesai, status (draft/dihitung/disetujui/dibayar/ditutup); unik(cabang\_id, tahun, bulan)
- `slip_gaji`: periode\_id, pegawai\_id, total\_pendapatan, total\_potongan, pph21, gaji\_bersih, status, tanggal\_bayar?, rekening\_pegawai\_id?
- `slip_gaji_rincian`: slip\_id, komponen\_id?, nama, tipe, nominal, keterangan?. Menyimpan salinan nama dan nominal agar slip lama tidak berubah bila komponen diubah.
- `pinjaman_pegawai`: pegawai\_id, nominal, cicilan\_per\_bulan, sisa, tanggal\_mulai, status. Cicilan masuk otomatis sebagai potongan.

## 4. Kesiswaan (F3)

### 4.1 Prestasi, kasus, konseling, alumni

- `prestasi_siswa`: siswa\_id, cabang\_id, tahun\_ajaran\_id, nama, bidang\_ref (akademik/olahraga/seni/tahfidz/lainnya), tingkat (sekolah/kecamatan/kota/provinsi/nasional/internasional), peringkat?, penyelenggara?, tanggal, pembina\_id?->pegawai, keterangan?. Bukti lewat lampiran.
- `jenis_pelanggaran`: kode (u), nama, kategori, jenjang\_id?, poin, tingkat (ringan/sedang/berat), is\_aktif
- `kasus_siswa`: siswa\_id, cabang\_id, tahun\_ajaran\_id, jenis\_pelanggaran\_id?, tanggal\_kejadian, uraian, poin, status (dilaporkan/ditangani/selesai), pelapor\_id->pegawai, penangan\_id?->pegawai, is\_rahasia
- `kasus_siswa_tindakan`: kasus\_id, tanggal, jenis\_tindakan (nasihat/panggil\_ortu/perjanjian/skorsing/lainnya), uraian, oleh\_id->pegawai, ortu\_dihubungi
- `konseling`: siswa\_id, cabang\_id, konselor\_id->pegawai, tanggal, topik, ringkasan (enkripsi), tindak\_lanjut?, is\_rahasia. Akses dibatasi peran konselor; setiap pembukaan tercatat di activity\_log.
- `alumni`: siswa\_id (u), tanggal\_lulus, sekolah\_lanjutan?, kontak\_alumni?, catatan?

### 4.2 Pembinaan karakter dan ibadah

- `aspek_karakter`: kode (u), nama, deskripsi?, jenjang\_id?, is\_aktif
- `catatan_karakter`: siswa\_id, cabang\_id, aspek\_id, tanggal, jenis (apresiasi/pembinaan), uraian, pencatat\_id->pegawai
- `capaian_tahfidz`: siswa\_id, cabang\_id, tanggal, jenis (ziyadah/murajaah), juz, surat, ayat\_awal, ayat\_akhir, nilai\_kelancaran?, penguji\_id->pegawai, catatan?
- `jenis_ibadah`: kode (u), nama, jenjang\_id?, is\_aktif. Contoh: shalat dhuha, tilawah, puasa sunnah.
- `catatan_ibadah`: siswa\_id, jenis\_ibadah\_id, tanggal, status (terlaksana/tidak), sumber (siswa/orang\_tua/guru), catatan?; unik(siswa\_id, jenis\_ibadah\_id, tanggal, sumber). Mutabaah harian yang bisa diisi orang tua lewat PWA.

### 4.3 Ekskul, minat dan bakat

- `ekskul`: sekolah\_id, nama, kategori\_ref, deskripsi?, is\_aktif
- `ekskul_kelompok`: ekskul\_id, cabang\_id, tahun\_ajaran\_id, semester\_id?, pembina\_id->pegawai, hari, jam\_mulai, jam\_selesai, tempat?, kuota?, status
- `ekskul_anggota`: kelompok\_id, siswa\_id, tanggal\_gabung, status, predikat?; unik(kelompok\_id, siswa\_id)
- `ekskul_pertemuan`: kelompok\_id, tanggal, materi?, catatan?
- `ekskul_kehadiran`: pertemuan\_id, siswa\_id, status (hadir/izin/sakit/alpa); unik(pertemuan\_id, siswa\_id)
- `minat_bakat`: siswa\_id, bidang\_ref, tingkat\_kemampuan?, sumber (angket/tes/pengamatan/orang\_tua), tanggal, catatan?

### 4.4 Kalender dan agenda

- `kalender_kegiatan`: tahun\_ajaran\_id, cabang\_id? (kosong = seluruh yayasan), sekolah\_id? (kosong = semua sekolah di cabang), judul, kategori (akademik/libur/ujian/kesiswaan/keagamaan/rapat/lainnya), tanggal\_mulai, tanggal\_selesai, jam\_mulai?, jam\_selesai?, lokasi?, deskripsi?, is\_libur, penanggung\_jawab\_id?->pegawai, visibilitas (internal/publik), status. Sumber hari efektif untuk absensi, jadwal, dan kalender publik.

## 5. Akademik (F3)

### 5.1 Kurikulum dan mata pelajaran

- `kurikulum`: kode (u), nama, deskripsi?, is\_aktif. Contoh: Kurikulum Merdeka, kurikulum khas yayasan.
- `kurikulum_sekolah`: kurikulum\_id, sekolah\_id, tahun\_ajaran\_id, tingkat\_id?; unik(sekolah\_id, tahun\_ajaran\_id, tingkat\_id). Kurikulum yang dipakai per sekolah, per tahun ajaran, dan (opsional) per tingkat.
- `mata_pelajaran`: kode (u), nama, kelompok\_ref (umum/agama/muatan\_lokal/pilihan), jenjang\_id?, is\_aktif
- `struktur_kurikulum`: kurikulum\_id, tingkat\_id, mata\_pelajaran\_id, jam\_per\_minggu, is\_wajib, kkm?; unik(kurikulum\_id, tingkat\_id, mata\_pelajaran\_id)
- `kompetensi_guru`: pegawai\_id, mata\_pelajaran\_id, tingkat\_id?, catatan?
- `tujuan_pembelajaran`: kurikulum\_id, mata\_pelajaran\_id, tingkat\_id, fase?, kode?, elemen?, uraian, urutan

### 5.2 Penugasan mengajar dan jadwal

- `penugasan_mengajar`: cabang\_id, sekolah\_id, tahun\_ajaran\_id, semester\_id, rombel\_id, mata\_pelajaran\_id, pegawai\_id, peran (utama/pendamping), jam\_per\_minggu, no\_sk?, status; unik(rombel\_id, mata\_pelajaran\_id, semester\_id, pegawai\_id). Pusat relasi akademik: jadwal, jurnal, penilaian, dan kelas LMS semua bergantung padanya.
- `jam_pelajaran`: sekolah\_id, tahun\_ajaran\_id, hari, urutan, jam\_mulai, jam\_selesai, tipe (belajar/istirahat/ibadah); unik(sekolah\_id, tahun\_ajaran\_id, hari, urutan)
- `jadwal_pelajaran`: cabang\_id, semester\_id, penugasan\_mengajar\_id, jam\_pelajaran\_id, ruang\_id?->ruang; unik(penugasan\_mengajar\_id, jam\_pelajaran\_id). Bentrok guru, rombel, dan ruang diperiksa di service; indeks mempercepat pemeriksaannya.

### 5.3 Presensi dan jurnal mengajar

- `jurnal_mengajar`: penugasan\_mengajar\_id, cabang\_id, tanggal, jam\_pelajaran\_id?, pegawai\_id (pengajar sebenarnya), materi, kegiatan?, catatan?, status (hadir/diganti/kosong)
- `presensi_siswa`: cabang\_id, rombel\_id, siswa\_id, tanggal, jurnal\_mengajar\_id?, status (hadir/sakit/izin/terlambat/alpa), keterangan?, dicatat\_oleh->users; unik(siswa\_id, tanggal, jurnal\_mengajar\_id). TK dan SD cukup presensi harian (jurnal\_mengajar\_id kosong); SMP per jam pelajaran.
- `izin_siswa`: siswa\_id, cabang\_id, tanggal\_mulai, tanggal\_selesai, jenis (sakit/izin), alasan?, diajukan\_oleh->orang, status

### 5.4 Perangkat ajar

- `modul_ajar`: cabang\_id, sekolah\_id, tahun\_ajaran\_id, semester\_id?, mata\_pelajaran\_id, tingkat\_id, pegawai\_id, judul, alokasi\_waktu?, isi (j), versi, status (draft/diajukan/disetujui/revisi). Persetujuan lewat approval\_permohonan.
- `modul_ajar_tujuan`: modul\_ajar\_id, tujuan\_pembelajaran\_id
- `modul_ajar_versi`: modul\_ajar\_id, versi, isi (j), dibuat\_oleh->users, catatan?

### 5.5 Penilaian, rapor, kenaikan kelas

Rapor tidak ditulis ke kolom tetap. Setiap sekolah memilih templat rapor (per jenjang dan kurikulum) yang tersusun dari bagian-bagian, dan isi rapor yang terbit disimpan sebagai butir generik. Mengganti kurikulum atau format rapor cukup mengubah data konfigurasi, tanpa migrasi.

- `komponen_penilaian`: sekolah\_id, tahun\_ajaran\_id, kurikulum\_id?, kode, nama, jenis (formatif/sumatif/uts/uas/sikap/proyek/praktik), bobot, urutan, is\_aktif; unik(sekolah\_id, tahun\_ajaran\_id, kurikulum\_id, kode). Bobot dan jenis komponen dapat berbeda per kurikulum dan per tahun.
- `penilaian`: penugasan\_mengajar\_id, semester\_id, komponen\_id->komponen\_penilaian, tujuan\_pembelajaran\_id?, judul, tanggal, nilai\_maks, status (draft/final), sumber (manual/tugas/cbt), sumber\_id?
- `nilai_siswa`: penilaian\_id, siswa\_id, nilai?, nilai\_remedial?, catatan?; unik(penilaian\_id, siswa\_id)
- `skala_penilaian`: sekolah\_id?, kurikulum\_id?, kode (u), nama, jenis (angka/predikat/capaian/deskriptif). Contoh: predikat A sampai D, atau capaian TK (Belum Muncul, Mulai Muncul, Berkembang, Membudaya).
- `skala_penilaian_butir`: skala\_id, kode, label, nilai\_min?, nilai\_maks?, urutan, deskripsi\_templat?. deskripsi\_templat menjadi draf kalimat deskripsi di rapor.
- `aturan_olah_nilai`: sekolah\_id, tahun\_ajaran\_id, kurikulum\_id?, tingkat\_id?, mata\_pelajaran\_id?, metode (rata\_berbobot/rata\_sumatif/nilai\_tertinggi/formula), formula? (j), pembulatan, kkm?, skala\_id?->skala\_penilaian. Aturan paling spesifik menang: mata pelajaran, lalu tingkat, lalu kurikulum.
- `nilai_akhir`: penugasan\_mengajar\_id, siswa\_id, semester\_id, nilai\_akhir?, skala\_butir\_id?->skala\_penilaian\_butir, deskripsi?, kkm?, is\_tuntas?, status (draft/final); unik(penugasan\_mengajar\_id, siswa\_id). Hasil olah per mata pelajaran; boleh berupa angka, predikat, atau deskripsi saja.
- `rapor_templat`: sekolah\_id?, jenjang\_id?, kurikulum\_id?, tahun\_ajaran\_id?, kode (u), nama, jenis\_penilaian (angka/deskriptif/campuran), pengaturan? (j: kop, tata letak, penandatangan), is\_aktif. Kolom konteks yang kosong berarti berlaku umum; templat paling spesifik menang.
- `rapor_templat_bagian`: templat\_id, kode, judul, tipe (nilai\_mapel/deskriptif\_mapel/deskriptif\_aspek/sikap/ekskul/kehadiran/proyek/tahfidz/pertumbuhan/catatan\_wali/kustom), urutan, konfigurasi? (j: sumber data, kolom yang tampil, skala\_id), is\_tampil. Menambah bagian rapor berarti menambah baris, bukan tabel atau kolom.
- `rapor`: siswa\_id, rombel\_id, semester\_id, cabang\_id, templat\_id->rapor\_templat, nomor? (u), catatan\_wali\_kelas?, status (draft/diverifikasi/terbit), ditandatangani\_oleh?->pegawai, tanggal\_terbit?, berkas\_path?, data\_tambahan? (j); unik(siswa\_id, semester\_id). Jumlah sakit, izin, alpa, dan peringkat bukan lagi kolom tetap: masuk sebagai butir bagian kehadiran atau data\_tambahan.
- `rapor_butir`: rapor\_id, bagian\_id->rapor\_templat\_bagian, urutan, label, ref\_type?, ref\_id?, nilai\_angka?, predikat?, deskripsi?, data? (j). Satu tabel untuk seluruh isi rapor: nilai mapel, deskripsi, sikap, ekskul, aspek TK, proyek, tahfidz. Dibuat sebagai salinan saat rapor terbit sehingga rapor lama tidak berubah bila data sumber diperbaiki.
- `keputusan_kenaikan`: siswa\_id, rombel\_id, tahun\_ajaran\_id, keputusan (naik/tinggal/lulus/pindah), rombel\_tujuan\_id?->rombel, catatan?, ditetapkan\_oleh->users, tanggal; unik(siswa\_id, tahun\_ajaran\_id). Dasar pembentukan rombel\_siswa tahun berikutnya.

### 5.6 Riwayat perkembangan siswa

- `perkembangan_siswa`: siswa\_id, cabang\_id, tahun\_ajaran\_id, semester\_id?, aspek\_ref (kognitif/bahasa/motorik/sosial\_emosional/karakter/ibadah/seni), uraian, skor?, pencatat\_id->pegawai, tanggal. Catatan naratif, terutama untuk TK dan SD kelas rendah.
- Linimasa siswa dibuat sebagai database view (v\_linimasa\_siswa) yang menggabungkan prestasi\_siswa, kasus\_siswa, rapor, catatan\_karakter, capaian\_tahfidz, dan perkembangan\_siswa. Bukan tabel.

### 5.7 Penilaian deskriptif TK

TK tidak memakai nilai angka. Penilaiannya berupa pengamatan harian yang dirangkum menjadi narasi per aspek di akhir semester. Tabel berikut memakai skala\_penilaian jenis capaian dan menjadi sumber bagian rapor bertipe deskriptif\_aspek.

- `tk_aspek`: kurikulum\_id, induk\_id?->tk\_aspek, kode, nama, urutan, is\_aktif. Aspek, elemen, atau sub-elemen dengan hierarki bebas, sehingga dapat mengikuti kurikulum mana pun yang dipakai TK.
- `tk_indikator`: aspek\_id, tingkat\_id, kode?, uraian, urutan, is\_aktif. Indikator atau capaian per tingkat (TK A, TK B).
- `tk_tema`: sekolah\_id, tahun\_ajaran\_id, semester\_id, nama, subtema?, tanggal\_mulai, tanggal\_selesai, urutan. Tema atau proyek pembelajaran.
- `tk_observasi`: siswa\_id, cabang\_id, rombel\_id, semester\_id, tanggal, tema\_id?, aspek\_id?, kegiatan?, uraian, interpretasi?, tindak\_lanjut?, pengamat\_id->pegawai. Catatan anekdot; foto lewat lampiran.
- `tk_ceklis_capaian`: siswa\_id, cabang\_id, semester\_id, indikator\_id, tanggal, skala\_butir\_id->skala\_penilaian\_butir, pengamat\_id->pegawai, catatan?, observasi\_id?->tk\_observasi. Setiap penilaian disimpan sebagai baris baru agar perkembangan dari waktu ke waktu terlihat.
- `tk_hasil_karya`: siswa\_id, cabang\_id, semester\_id, tema\_id?, aspek\_id?, tanggal, judul, uraian?, pengamat\_id->pegawai. Foto atau scan karya lewat lampiran.
- `tk_asesmen_semester`: siswa\_id, semester\_id, aspek\_id, skala\_butir\_id?->skala\_penilaian\_butir, deskripsi, disusun\_oleh->pegawai, status (draft/final); unik(siswa\_id, semester\_id, aspek\_id). Narasi per aspek untuk rapor, disusun guru dari observasi, ceklis, dan hasil karya; deskripsi\_templat dari skala menjadi draf awal.
- `tk_refleksi_orang_tua`: siswa\_id, semester\_id, orang\_id, tanggal, uraian
- Bagian rapor TK lain mengambil data dari modul lain lewat konfigurasi templat: pertumbuhan dari pemeriksaan\_kesehatan (tinggi dan berat badan), kehadiran dari presensi\_siswa, tahfidz dari capaian\_tahfidz. perkembangan\_siswa (5.6) tetap dipakai untuk catatan umum lintas jenjang.

## 6. LMS, Bank Soal, CBT (F4)

### 6.1 Materi dan kelas belajar

- `materi_ajar`: pegawai\_id, cabang\_id, mata\_pelajaran\_id, tingkat\_id, judul, jenis (dokumen/video/presentasi/tautan/teks), isi?, tautan?, tag? (j), cakupan (pribadi/sekolah/cabang/yayasan), status. File lewat lampiran. Cakupan yayasan memungkinkan berbagi materi lintas cabang.
- `kelas_belajar`: penugasan\_mengajar\_id (u), judul, deskripsi?, kode\_gabung?, status
- `kelas_topik`: kelas\_belajar\_id, judul, urutan, terbit\_pada?
- `kelas_konten`: topik\_id, konten\_type (materi\_ajar/tugas/ujian/diskusi), konten\_id, urutan, terbit\_pada?, tenggat\_pada?
- `kelas_progres`: siswa\_id, kelas\_konten\_id, status (dibuka/selesai), selesai\_pada?; unik(siswa\_id, kelas\_konten\_id)
- `tugas`: kelas\_belajar\_id, judul, instruksi, mulai\_pada, tenggat\_pada, nilai\_maks, jenis\_pengumpulan (file/teks/keduanya), penilaian\_id?, status
- `tugas_pengumpulan`: tugas\_id, siswa\_id, dikumpulkan\_pada?, teks?, nilai?, umpan\_balik?, status (belum/diserahkan/terlambat/dinilai); unik(tugas\_id, siswa\_id)
- `diskusi`: kelas\_belajar\_id, judul, dibuat\_oleh->users, status
- `diskusi_pesan`: diskusi\_id, user\_id, parent\_id?->diskusi\_pesan, isi

### 6.2 Bank soal

Soal disimpan sebagai konten terstruktur (blok), bukan teks bebas, agar formula, gambar, dan layout tampil sama di layar CBT, hasil cetak PDF, dan hasil ekspor. Kunci dan jawaban dipisah per bentuk soal sehingga semua bentuk, kecuali uraian, dapat diskor otomatis. Bentuk soal mengikuti ANBK/AKM dan TKA: pilihan ganda, pilihan ganda kompleks (memilih lebih dari satu, atau kategori benar/salah per pernyataan), menjodohkan, isian singkat, isian angka, dan uraian.

- `bank_soal`: cabang\_id?, sekolah\_id?, mata\_pelajaran\_id?, tingkat\_id?, nama, deskripsi?, pemilik\_id->pegawai, cakupan (pribadi/sekolah/cabang/yayasan), status. Wadah soal seperti file bank soal di ExamView; satu guru atau tim bisa punya beberapa bank.
- `soal_topik`: induk\_id?->soal\_topik, mata\_pelajaran\_id, tingkat\_id?, kode?, nama, urutan. Hierarki bab dan subbab, atau konten dan domain AKM (misalnya bilangan, geometri, teks informasi).
- `soal_stimulus`: bank\_id, pembuat\_id->pegawai, judul?, konten (j). Wacana, gambar, tabel, atau grafik bersama yang dipakai satu atau banyak soal (soal grup pada TKA; case pada ExamView).
- `soal`: bank\_id, cabang\_id, pembuat\_id->pegawai, mata\_pelajaran\_id, tingkat\_id, topik\_id?->soal\_topik, stimulus\_id?->soal\_stimulus, urutan\_stimulus?, jenis (pilihan\_ganda/pg\_kompleks\_pilih/pg\_kompleks\_kategori/menjodohkan/isian\_singkat/isian\_angka/uraian), konten (j), pembahasan? (j), skor\_maks, aturan\_skor? (j), konfigurasi? (j), parameter? (j), tingkat\_kesulitan (mudah/sedang/sulit), level\_kognitif\_ref?, cakupan (pribadi/sekolah/cabang/yayasan), status (draft/telaah/aktif/arsip), versi, tag? (j), teks\_polos, kali\_dipakai (s). konten dan pembahasan berformat blok JSON (paragraf, formula LaTeX, gambar, tabel, daftar, kolom). konfigurasi memuat pengaturan khusus jenis (acak opsi, jumlah kolom opsi, label kategori). aturan\_skor menyimpan skor sebagian, misalnya PG kompleks kategori: semua benar 2, salah satu atau dua pernyataan 1, selebihnya 0. parameter berisi variabel acak untuk soal algoritmik (opsional). teks\_polos untuk pencarian.
- `soal_opsi`: soal\_id, kode, sisi? (kiri/kanan, hanya menjodohkan), konten (j), is\_benar?, kunci\_kategori?, urutan, is\_tetap (tidak diacak). Satu tabel untuk pilihan PG, pilihan PG kompleks, pernyataan PG kompleks kategori (kunci\_kategori = kategori yang benar, misalnya Benar atau Salah), serta butir kiri dan kanan menjodohkan.
- `soal_kunci_pasangan`: soal\_id, opsi\_kiri\_id->soal\_opsi, opsi\_kanan\_id->soal\_opsi. Kunci menjodohkan; mendukung satu-ke-satu maupun satu-ke-banyak.
- `soal_kunci_isian`: soal\_id, tipe (teks/angka/rentang), nilai\_teks?, nilai\_angka?, toleransi?, nilai\_min?, nilai\_maks?, satuan?, peka\_huruf, abaikan\_spasi, skor?. Beberapa baris berarti beberapa jawaban diterima. Jawaban angka dinormalisasi lebih dulu, sehingga 0,5 dan 0.5 dianggap sama.
- `soal_rubrik`: soal\_id, urutan, kriteria, deskripsi?, skor. Rubrik penskoran uraian; jawaban acuan disimpan di konfigurasi soal.
- `soal_tujuan`: soal\_id, tujuan\_pembelajaran\_id; unik(soal\_id, tujuan\_pembelajaran\_id)
- `soal_versi`: soal\_id, versi, snapshot (j), dibuat\_oleh->users. Salinan beku soal beserta opsi dan kuncinya, dibuat saat soal masuk paket final agar ujian lama tidak berubah bila soal diperbaiki.
- `soal_telaah`: soal\_id, penelaah\_id->pegawai, status (setuju/revisi/tolak), catatan?, tanggal
- `soal_statistik`: soal\_id (u), jumlah\_dijawab, jumlah\_benar, indeks\_kesukaran?, daya\_beda?, dihitung\_pada. Dihitung job dari hasil ujian.
- `impor_soal`: bank\_id, sumber (docx/xlsx/json/qti), status (antri/proses/selesai/gagal), ringkasan? (j), galat? (j), diminta\_oleh->users. Berkas lewat lampiran; soal hasil impor berstatus draft untuk ditelaah.

### 6.3 CBT

- `ujian`: cabang\_id, sekolah\_id, tahun\_ajaran\_id, semester\_id, mata\_pelajaran\_id, paket\_soal\_id->paket\_soal, penugasan\_mengajar\_id?, penilaian\_id?, pembuat\_id->pegawai, judul, moda (cbt/tertulis), jenis (kuis/harian/uts/uas/tryout/seleksi\_spmb), mulai\_pada, selesai\_pada, durasi\_menit, maks\_percobaan, nilai\_kkm?, tampilkan\_hasil, pengaturan? (j: navigasi bebas atau berurutan, kalkulator, acak soal dan opsi, layar penuh), token? (enkripsi), status. Moda tertulis dipakai bila siswa mengerjakan di kertas dan guru memasukkan jawabannya.
- `ujian_peserta`: ujian\_id, siswa\_id?, pendaftar\_id?->spmb\_pendaftar, percobaan\_ke, versi\_id?->paket\_soal\_versi, status (belum/berlangsung/selesai/dibatalkan), mulai\_pada?, selesai\_pada?, urutan\_soal? (j), alamat\_ip?, perangkat?, nilai?, jumlah\_pelanggaran. Salah satu dari siswa\_id atau pendaftar\_id terisi (check constraint), supaya CBT bisa dipakai untuk tes seleksi SPMB.
- `ujian_jawaban`: peserta\_id, butir\_id->paket\_soal\_butir, jawaban? (j), skor?, status\_koreksi (otomatis/menunggu/dikoreksi), is\_benar?, dinilai\_oleh?->pegawai, catatan?, ditandai\_ragu, dijawab\_pada; unik(peserta\_id, butir\_id). Bentuk jawaban menurut jenis soal: pilihan\_ganda = kode opsi; pg\_kompleks\_pilih = daftar kode opsi; pg\_kompleks\_kategori = peta pernyataan ke kategori; menjodohkan = daftar pasangan; isian = teks atau angka; uraian = teks atau lampiran.
- `ujian_pelanggaran`: peserta\_id, jenis (pindah\_tab/keluar\_layar/koneksi\_putus/lainnya), waktu, detail? (j)
- Penskoran lewat satu service per jenis soal: bentuk objektif diskor otomatis (termasuk skor sebagian menurut aturan\_skor), uraian menunggu penskor memakai rubrik.
- Nilai CBT mengisi nilai\_siswa lewat event UjianSelesaiDinilai (penilaian.sumber = cbt, sumber\_id = ujian\_id).

### 6.4 Penyusunan paket soal dan ekspor (seperti ExamView)

Paket soal setara satu tes di ExamView Test Generator: guru memilih soal dari bank (manual atau acak menurut kriteria), menyusunnya dalam bagian, membuat versi A sampai Z, lalu mencetak naskah, kunci jawaban, dan lembar jawaban, atau memakainya langsung di CBT.

- `paket_soal`: cabang\_id, sekolah\_id?, mata\_pelajaran\_id, tingkat\_id, judul, subjudul?, pembuat\_id->pegawai, petunjuk? (j), pengaturan\_cetak? (j: ukuran kertas, jumlah kolom, font, margin, header, footer, nomor halaman, ruang jawaban), templat\_cetak\_id?->templat\_cetak, is\_sementara, status (draft/final/diarsipkan)
- `paket_soal_bagian`: paket\_id, urutan, judul, petunjuk? (j), acak\_butir. Bagian biasanya dikelompokkan per bentuk soal, lengkap dengan petunjuk pengerjaannya.
- `paket_soal_butir`: paket\_id, bagian\_id, soal\_versi\_id->soal\_versi, urutan, skor (menimpa skor\_maks), is\_nomor\_tetap
- `paket_soal_aturan`: paket\_id, bagian\_id?, kriteria (j: bank, topik, jenis, tingkat kesulitan, level kognitif, tag), jumlah, skor\_per\_butir?. Pemilihan acak menurut kriteria atau kisi-kisi; hasilnya masuk ke paket\_soal\_butir dan masih bisa diubah manual.
- `paket_soal_versi`: paket\_id, kode (A sampai Z), seed, urutan (j: urutan bagian, butir, dan opsi), dibuat\_pada; unik(paket\_id, kode). Dibangkitkan deterministik dari seed sehingga naskah, kunci, dan lembar jawaban tiap versi selalu cocok; untuk soal algoritmik nilai variabel ikut disimpan.
- `templat_cetak`: cabang\_id?, sekolah\_id?, nama, jenis (naskah/kunci/lembar\_jawaban/kisi\_kisi/daftar\_soal), konfigurasi (j: kop, logo, font, kolom, margin, header, footer), is\_default
- `ekspor_dokumen`: paket\_id?, bank\_id?, versi\_id?, jenis (naskah/kunci\_jawaban/lembar\_jawaban/kisi\_kisi/pembahasan/daftar\_soal), format (pdf/docx/html/json), acak? (j: acak\_butir, acak\_opsi, jumlah\_versi, seed), kriteria? (j: pemilihan acak langsung dari bank), opsi? (j: dengan pembahasan, kunci terpisah, tanpa gambar, penomoran), status (antri/proses/selesai/gagal), galat?, diminta\_oleh->users, selesai\_pada?. Berkas hasil lewat lampiran; dikerjakan job antrean. Satu ekspor menghasilkan satu berkas per versi, dibundel zip bila lebih dari satu. Ekspor langsung dari bank (tanpa paket) membuat paket\_soal sementara agar urutan dan kunci tiap versi tetap tersimpan.

### 6.5 Catatan teknis bank soal

- Editor soal memakai TipTap/ProseMirror dengan ekstensi formula (KaTeX) dan gambar. Formula disimpan sebagai LaTeX di dalam blok JSON, bukan sebagai gambar, sehingga bisa dicari, diedit, dan dirender tajam.
- Blok gambar menyimpan lampiran\_id, lebar (persen), perataan (kiri/kanan/tengah/sebaris), dan keterangan. Layout fleksibel seperti gambar di samping teks atau dua kolom memakai blok kolom.
- Satu sumber, dua keluaran: konten JSON dirender menjadi HTML (dengan KaTeX). Layar CBT memakai HTML itu; PDF dibuat dengan merender HTML yang sama lewat Chromium tanpa kepala (misalnya kontainer Gotenberg atau spatie/browsershot). Pagination, kolom, dan formula jadi akurat dan identik dengan tampilan layar. Soal dijaga tidak terpotong antarhalaman dan soal grup tetap berdekatan dengan stimulusnya.
- Pencarian bank soal memakai kolom teks\_polos dan indeks Meilisearch.
- Fitur ExamView yang ditiru: bank per mata pelajaran dan bab, pemilihan soal manual atau acak menurut kriteria, versi A sampai Z, pengacakan butir dan opsi, kunci jawaban per versi, lembar jawaban, tata letak cetak yang dapat diatur, serta impor dan ekspor. Belum tercakup: pemindaian lembar jawaban (OMR) dan grafik dinamis.

### 6.6 Ekspor PDF dan DOCX dengan nomor soal teracak

Dari satu bank atau paket, guru dapat mengunduh PDF (untuk dicetak sebagai tes tertulis) dan DOCX (dapat diedit di Word), dalam satu atau banyak versi yang urutan soal dan opsinya berbeda.

- Pengacakan: seed tiap versi menentukan urutan bagian, butir, dan opsi. Nomor soal pada hasil ekspor selalu dinomori ulang berurutan, jadi soal yang tampil sebagai nomor 1 tidak harus soal pertama di bank. Butir bertanda is\_nomor\_tetap, opsi bertanda is\_tetap, dan soal dalam satu stimulus tetap pada tempat dan urutannya.
- Kunci selalu cocok: urutan tiap versi disimpan di paket\_soal\_versi.urutan, sehingga kunci jawaban, lembar jawaban, dan pemetaan nomor ke soal asli dapat dibuat ulang kapan saja. Hasil tes tertulis dimasukkan guru berdasarkan versi yang sama.
- Dua jalur ekspor: dari paket (susunan sudah dipilih guru), atau langsung dari bank dengan kriteria (misalnya 10 soal mudah topik pecahan, 5 sedang, 2 uraian). Jalur kedua membuat paket sementara otomatis.
- PDF: dirender dari HTML yang sama dengan tampilan CBT lewat Chromium tanpa kepala (bagian 6.5), sehingga formula, gambar, kolom, dan pemisah halaman akurat.
- DOCX: konten JSON diubah menjadi LaTeX dan HTML, lalu dikonversi dengan Pandoc memakai berkas reference docx dari templat\_cetak (kop, font, margin, gaya). Formula menjadi persamaan Word asli yang dapat diedit, dan gambar disematkan. Keterbatasan: layout rumit (gambar mengambang dengan teks membungkus, kolom bersarang) disederhanakan di DOCX; PDF tetap menjadi acuan tampilan. Sebelum dipastikan, uji dengan sekitar 20 contoh soal nyata (formula, gambar, tabel). Bila hasilnya kurang, alternatifnya menyusun DOCX langsung dengan library docx.
- Penomoran DOCX: opsi penomoran pada ekspor\_dokumen.opsi memilih teks biasa atau daftar bernomor otomatis Word. Daftar otomatis disarankan agar nomor ikut menyesuaikan bila guru menghapus atau memindahkan soal di Word.
- Setiap berkas memuat kode paket dan kode versi di footer (opsional QR) agar mudah dicocokkan dengan kunci.
- Server job membutuhkan Chromium dan Pandoc di kontainer worker; kedua alat itu dipasang di image Docker, bukan di aplikasi Laravel.

## 7. Keuangan (F2)

Pembukuan dipisah per entitas akuntansi: setiap sekolah, setiap cabang, dan yayasan masing-masing punya buku sendiri, lalu dikonsolidasikan ke atas. Transaksi dicatat di entitas yang menanggungnya: SPP dan belanja sekolah di entitas sekolah, biaya bersama satu cabang di entitas cabang, biaya pusat di entitas yayasan. Tabel transaksi yang menghasilkan jurnal membawa entitas\_id di samping cabang\_id; nilainya diisi service dari sekolah atau unit kerja pemilik transaksi. Semua entitas memakai bagan akun standar yayasan agar laporan dapat dijumlahkan.

### 7.1 Akuntansi dasar

- `entitas_akuntansi`: induk\_id?->entitas\_akuntansi, tipe (yayasan/cabang/sekolah), cabang\_id?, sekolah\_id?, kode (u), nama, is\_aktif. Pohon tiga tingkat: yayasan, cabang, sekolah. Entitas sekolah dibuat otomatis untuk setiap baris sekolah. Check: tipe cabang mengisi cabang\_id, tipe sekolah mengisi sekolah\_id.
- `akun`: entitas\_id? (kosong = bagan akun standar yayasan yang berlaku di semua entitas; terisi = akun lokal), kode, nama, tipe (aset/kewajiban/ekuitas/pendapatan/beban), induk\_id?->akun, saldo\_normal (debit/kredit), is\_header, is\_antar\_entitas, is\_aktif; unik(entitas\_id, kode) dengan NULLS NOT DISTINCT. is\_antar\_entitas menandai akun piutang, utang, pendapatan, dan beban antar entitas yang dieliminasi saat konsolidasi.
- `pusat_biaya`: entitas\_id, unit\_kerja\_id?, kode, nama, is\_aktif
- `periode_akuntansi`: entitas\_id, tahun, bulan, status (terbuka/ditutup); unik(entitas\_id, tahun, bulan). Tiap entitas menutup bukunya sendiri; konsolidasi baru dikunci setelah semua entitas di bawahnya ditutup.
- `jurnal`: entitas\_id, cabang\_id? (denormalisasi untuk global scope; kosong untuk yayasan), periode\_id->periode\_akuntansi, nomor (u), tanggal, keterangan, sumber\_type?, sumber\_id?, status (draft/posted/void), dibuat\_oleh->users, diposting\_oleh?->users, diposting\_pada?. Jurnal posted tidak boleh diubah; koreksi lewat jurnal pembalik. Periode harus milik entitas yang sama dengan jurnal.
- `jurnal_baris`: jurnal\_id, akun\_id, pusat\_biaya\_id?, debit, kredit, keterangan?. Check: salah satu dari debit atau kredit bernilai nol. Total debit sama dengan total kredit per jurnal, divalidasi di service (dan trigger bila perlu).
- `saldo_akun_periode`: entitas\_id, akun\_id, periode\_id->periode\_akuntansi, debit, kredit, saldo\_akhir; unik(entitas\_id, akun\_id, periode\_id). Snapshot per entitas saat periode ditutup. Saldo cabang dan yayasan dihitung dari snapshot entitas di bawahnya lewat view rekursif (v\_saldo\_konsolidasi), dikurangi eliminasi.
- `pemetaan_akun`: entitas\_id?, kunci, akun\_id; unik(entitas\_id, kunci) dengan NULLS NOT DISTINCT. Menentukan akun otomatis untuk tiap peristiwa (misalnya pembayaran SPP ke akun pendapatan SPP). Pemetaan default berada di yayasan dan dapat ditimpa per entitas.
- `transaksi_antar_entitas`: nomor (u), dari\_entitas\_id->entitas\_akuntansi, ke\_entitas\_id->entitas\_akuntansi, tanggal, jenis (subsidi/pinjaman/setoran/alokasi\_biaya/penyelesaian), nominal, keterangan, jurnal\_dari\_id->jurnal, jurnal\_ke\_id->jurnal, status (diajukan/disetujui/selesai). Satu transaksi menghasilkan dua jurnal bersisian di kedua entitas dan menjadi dasar eliminasi konsolidasi. Check: dari\_entitas\_id berbeda dari ke\_entitas\_id. Persetujuan lewat approval.
- `aturan_alokasi_biaya`: entitas\_sumber\_id->entitas\_akuntansi, nama, akun\_id?, dasar (persen\_tetap/jumlah\_siswa/pendapatan/jumlah\_pegawai), rincian (j: entitas tujuan dan bobotnya), is\_aktif. Membagikan biaya pusat atau biaya cabang ke entitas di bawahnya lewat transaksi\_antar\_entitas.
- Laporan neraca, laba rugi, dan arus kas tersedia per sekolah, per cabang (gabungan sekolah di bawahnya), dan konsolidasi yayasan, semuanya dari pohon entitas yang sama.

### 7.2 Kas dan bank

- `rekening_kas`: entitas\_id, cabang\_id?, nama, tipe (kas/bank), bank\_ref?, nomor\_rekening?, akun\_id, saldo\_awal, is\_aktif. Satu rekening dimiliki satu entitas; rekening bersama (misalnya rekening operasional cabang) dimiliki entitas cabang dan disalurkan ke sekolah lewat transaksi\_antar\_entitas.
- `transaksi_kas`: entitas\_id, cabang\_id?, rekening\_id->rekening\_kas, tanggal, tipe (masuk/keluar/transfer), nominal, akun\_lawan\_id?->akun, rekening\_tujuan\_id?->rekening\_kas, keterangan, sumber\_type?, sumber\_id?, jurnal\_id?, rekonsiliasi\_id?, status
- `rekonsiliasi_bank`: rekening\_id->rekening\_kas, tahun, bulan, saldo\_bank, saldo\_buku, selisih, status

### 7.3 Penerimaan dari siswa

- `jenis_tagihan`: kode (u), nama, tipe (bulanan/tahunan/sekali/uang\_pangkal/seragam/lainnya), akun\_pendapatan\_id->akun, jenjang\_id?, boleh\_cicil, is\_aktif
- `tarif_tagihan`: jenis\_tagihan\_id, sekolah\_id, tahun\_ajaran\_id, tingkat\_id?, nominal, jadwal? (j)
- `diskon_beasiswa`: kode (u), nama, tipe (persen/nominal), nilai, sumber\_dana?, kriteria?, is\_aktif
- `siswa_diskon`: siswa\_id, diskon\_id->diskon\_beasiswa, jenis\_tagihan\_id?, tanggal\_mulai, tanggal\_akhir?, no\_sk?, status
- `tagihan`: cabang\_id, entitas\_id, siswa\_id?, pendaftar\_id?->spmb\_pendaftar, tahun\_ajaran\_id, jenis\_tagihan\_id, nomor (u), periode\_label?, tanggal\_terbit, jatuh\_tempo, nominal, diskon, total, terbayar, status (belum/sebagian/lunas/batal). Check: salah satu dari siswa\_id atau pendaftar\_id terisi. Sisa = total - terbayar.
- `pembayaran`: cabang\_id, entitas\_id, nomor (u), siswa\_id?, pendaftar\_id?->spmb\_pendaftar, tanggal, metode (tunai/transfer/virtual\_account/qris/gateway), nominal, rekening\_id->rekening\_kas, referensi\_eksternal?, status (menunggu/berhasil/gagal/batal), diterima\_oleh?->users, transaksi\_kas\_id?
- `pembayaran_alokasi`: pembayaran\_id, tagihan\_id, nominal. Selisih antara pembayaran.nominal dan jumlah alokasi adalah kelebihan bayar, yang dapat dialokasikan ke tagihan berikutnya.
- `virtual_account`: siswa\_id, bank\_ref, nomor (u), status, kedaluwarsa\_pada?
- `pembayaran_gateway_log`: penyedia, referensi (u), payload (j), status\_proses, diterima\_pada. Semua callback disimpan sebelum diproses agar aman dari duplikasi dan mudah ditelusuri.
- `pengembalian_dana`: pembayaran\_id, nominal, alasan, tanggal, status. Melalui approval.

### 7.4 Anggaran

- `anggaran`: cabang\_id, entitas\_id, sekolah\_id?, unit\_kerja\_id?, tahun\_ajaran\_id, nama, versi, induk\_id?->anggaran (revisi), total, status (draft/diajukan/disetujui/ditutup)
- `anggaran_baris`: anggaran\_id, akun\_id, pusat\_biaya\_id?, uraian, volume, satuan\_ref, harga\_satuan, jumlah, bulan\_rencana?
- Realisasi anggaran dibuat sebagai view (v\_realisasi\_anggaran) dari jurnal\_baris dan pengajuan\_dana, bukan kolom yang diperbarui manual.

### 7.5 Pengeluaran

- `pengajuan_dana`: cabang\_id, entitas\_id, nomor (u), unit\_kerja\_id, pemohon\_id->pegawai, anggaran\_baris\_id?, tanggal, judul, keperluan?, total, prioritas, status (draft/diajukan/disetujui/ditolak/dicairkan/dipertanggungjawabkan/selesai). Persetujuan bertingkat memakai approval dengan batas nominal.
- `pengajuan_dana_baris`: pengajuan\_id, akun\_id, anggaran\_baris\_id?, barang\_id?, uraian, volume, satuan\_ref, harga\_satuan, jumlah
- `pencairan_dana`: pengajuan\_id, tanggal, nominal, rekening\_id->rekening\_kas, penerima\_id->orang, metode, transaksi\_kas\_id?, status
- `pertanggungjawaban`: pencairan\_id, tanggal, total\_realisasi, sisa\_dikembalikan, status
- `pertanggungjawaban_baris`: pertanggungjawaban\_id, uraian, nominal, nomor\_bukti?, tanggal\_bukti?. Foto atau scan bukti lewat lampiran.

### 7.6 Pembelian dan penjualan

- `pemasok`: kode (u), nama, alamat?, telepon?, email?, npwp?, nama\_kontak?, bank\_ref?, nomor\_rekening?, status (s)
- `pesanan_pembelian`: cabang\_id, entitas\_id, nomor (u), pemasok\_id, pengajuan\_dana\_id?, tanggal, tanggal\_dibutuhkan?, total, status (draft/disetujui/dipesan/diterima\_sebagian/diterima/batal)
- `pesanan_pembelian_baris`: pesanan\_id, barang\_id?->barang, uraian, jumlah, satuan\_ref, harga\_satuan, jumlah\_diterima
- `faktur_pembelian`: cabang\_id, entitas\_id, pemasok\_id, pesanan\_id?->pesanan\_pembelian, nomor\_faktur, tanggal, jatuh\_tempo, total, terbayar, status, jurnal\_id?
- `pembayaran_faktur`: faktur\_id->faktur\_pembelian, tanggal, nominal, rekening\_id->rekening\_kas, transaksi\_kas\_id?
- `penjualan`: cabang\_id, entitas\_id, nomor (u), tanggal, pembeli\_tipe (siswa/pegawai/umum), siswa\_id?, pegawai\_id?, nama\_pembeli?, total, metode\_bayar, tagihan\_id?, status. Untuk seragam, buku, dan barang lain; stok berkurang lewat event.
- `penjualan_baris`: penjualan\_id, barang\_id, gudang\_id, jumlah, harga, diskon

### 7.7 Dana sosial

- `donatur`: orang\_id?, nama, telepon?, email?, jenis (perorangan/lembaga)
- `donasi`: cabang\_id, entitas\_id, donatur\_id?, jenis (infak/sedekah/wakaf/zakat), program?, nominal, tanggal, rekening\_id->rekening\_kas, is\_anonim, status, transaksi\_kas\_id?

### 7.8 Audit

- `audit_temuan`: cabang\_id, entitas\_id?, tahun, sumber (internal/eksternal), auditor?, judul, uraian, tingkat\_risiko, rekomendasi?, penanggung\_jawab\_id?->pegawai, tenggat?, status (terbuka/proses/selesai)
- `audit_tindak_lanjut`: temuan\_id->audit\_temuan, tanggal, uraian, oleh\_id->pegawai, status
- Jejak siapa mengubah apa tetap berasal dari activity\_log; jurnal posted dan periode tertutup memberi kontrol tambahan.

## 8. Inventaris (F1)

### 8.1 Tanah, bangunan, ruang

- `tanah`: cabang\_id, entitas\_id, kode\_aset (u), nama, alamat?, wilayah\_id?, luas\_m2, nomor\_sertifikat?, jenis\_hak? (shm/hgb/wakaf/sewa), atas\_nama?, asal\_perolehan (beli/wakaf/hibah/sewa), tanggal\_perolehan?, nilai\_perolehan?, latitude?, longitude?, status (s)
- `bangunan`: cabang\_id, entitas\_id, tanah\_id, kode\_aset (u), nama, luas\_m2, jumlah\_lantai, tahun\_dibangun?, nomor\_pbg?, kondisi, nilai\_perolehan?, status (s)
- `ruang`: cabang\_id, sekolah\_id?, bangunan\_id?, kode, nama, lantai?, jenis\_ref (kelas/lab/perpustakaan/kantor/masjid/aula/gudang/lainnya), luas\_m2?, kapasitas?, kondisi, penanggung\_jawab\_id?->pegawai, status; unik(cabang\_id, kode)

### 8.2 Katalog dan aset tetap

- `kategori_aset`: induk\_id?->kategori\_aset, kode (u), nama, jenis (aset\_tetap/habis\_pakai/tanah/bangunan), masa\_manfaat\_tahun?, metode\_penyusutan? (garis\_lurus/tidak\_disusutkan), akun\_aset\_id?->akun, akun\_penyusutan\_id?->akun, akun\_beban\_id?->akun
- `barang`: kode (u), nama, kategori\_id->kategori\_aset, jenis (aset\_tetap/habis\_pakai), satuan\_ref, merek?, spesifikasi?, harga\_standar?, stok\_minimum?, is\_aktif (s). Katalog bersama seluruh cabang.
- `aset_unit`: cabang\_id, entitas\_id, barang\_id, kode\_aset (u), nomor\_seri?, tanggal\_perolehan, harga\_perolehan, sumber\_dana\_ref (yayasan/bos/hibah/wakaf/lainnya), penerimaan\_barang\_baris\_id?, nilai\_residu, masa\_manfaat\_tahun?, kondisi (baik/rusak\_ringan/rusak\_berat), status (aktif/dipinjam/perbaikan/dihapus), ruang\_id?, penanggung\_jawab\_id?->pegawai, kode\_qr (u), foto\_path? (s). Satu baris per unit fisik; label QR dipindai untuk cek, pinjam, dan opname.
- `aset_kendaraan`: aset\_unit\_id (u), nomor\_polisi, nomor\_rangka?, nomor\_mesin?, masa\_berlaku\_stnk?, masa\_berlaku\_pajak?
- `penyusutan_aset`: aset\_type (aset\_unit/bangunan), aset\_id, periode\_id->periode\_akuntansi, nilai\_penyusutan, akumulasi, nilai\_buku, jurnal\_id?; unik(aset\_type, aset\_id, periode\_id)

### 8.3 Barang habis pakai dan stok

- `gudang`: cabang\_id, kode, nama, ruang\_id?, penanggung\_jawab\_id?->pegawai, is\_aktif; unik(cabang\_id, kode)
- `stok_barang`: gudang\_id, barang\_id, jumlah; unik(gudang\_id, barang\_id). Diperbarui hanya lewat mutasi\_stok.
- `mutasi_stok`: gudang\_id, barang\_id, tanggal, tipe (masuk/keluar/transfer\_masuk/transfer\_keluar/penyesuaian), jumlah, harga\_satuan?, saldo\_setelah, sumber\_type, sumber\_id, dibuat\_oleh->users. Buku besar stok: hanya ditambah, tidak diubah.
- `penerimaan_barang`: cabang\_id, nomor (u), pesanan\_pembelian\_id?, pemasok\_id?, gudang\_id, tanggal, nomor\_surat\_jalan?, diterima\_oleh->pegawai, status. Untuk barang aset\_tetap, service membuat aset\_unit sebanyak jumlah; untuk habis\_pakai, service menambah stok.
- `penerimaan_barang_baris`: penerimaan\_id, barang\_id, jumlah, harga\_satuan, kondisi
- `permintaan_barang`: cabang\_id, nomor (u), unit\_kerja\_id, pemohon\_id->pegawai, tanggal, keperluan?, status
- `permintaan_barang_baris`: permintaan\_id, barang\_id, jumlah\_diminta, jumlah\_disetujui?
- `pengeluaran_barang`: cabang\_id, nomor (u), gudang\_id, permintaan\_id?, penerima\_id->pegawai, tanggal, status
- `pengeluaran_barang_baris`: pengeluaran\_id, barang\_id, jumlah
- `stok_opname`: gudang\_id, tanggal, status (draft/selesai), dibuat\_oleh->users
- `stok_opname_baris`: opname\_id->stok\_opname, barang\_id, stok\_sistem, stok\_fisik, selisih, catatan?

### 8.4 Peminjaman, mutasi, pemeliharaan, penghapusan

- `peminjaman`: cabang\_id, nomor (u), peminjam\_id->orang, unit\_kerja\_id?, keperluan, tanggal\_pinjam, tanggal\_rencana\_kembali, tanggal\_kembali?, status (diajukan/disetujui/dipinjam/kembali/terlambat/hilang)
- `peminjaman_baris`: peminjaman\_id, aset\_unit\_id, kondisi\_keluar, kondisi\_kembali?, catatan?
- `reservasi_ruang`: ruang\_id, peminjam\_id->orang, keperluan, mulai\_pada, selesai\_pada, status. Dicek terhadap jadwal\_pelajaran dan reservasi lain agar tidak bentrok.
- `mutasi_aset`: nomor (u), cabang\_asal\_id->cabang, cabang\_tujuan\_id->cabang, ruang\_tujuan\_id?->ruang, tanggal, alasan, status (diajukan/disetujui/selesai). Pemindahan antarruang dalam satu cabang memakai cabang asal dan tujuan yang sama.
- `mutasi_aset_baris`: mutasi\_id, aset\_unit\_id, ruang\_asal\_id?->ruang, kondisi
- `laporan_kerusakan`: cabang\_id, pelapor\_id->orang, aset\_unit\_id?, ruang\_id?, uraian, prioritas, status (baru/ditangani/selesai)
- `pemeliharaan_aset`: cabang\_id, aset\_unit\_id?, ruang\_id?, laporan\_kerusakan\_id?, jenis (rutin/perbaikan), tanggal, uraian, biaya?, pelaksana?, tanggal\_berikutnya?, status
- `penghapusan_aset`: cabang\_id, aset\_unit\_id, tanggal, alasan (rusak\_berat/hilang/dijual/dihibahkan), nilai\_buku, nilai\_jual?, no\_berita\_acara?, penjualan\_id?, status. Melalui approval; memicu jurnal pelepasan aset di Keuangan.

## 9. SPMB (F1)

### 9.1 Periode, prospek, dan follow up

- `spmb_periode`: cabang\_id, sekolah\_id, tahun\_ajaran\_id, nama, gelombang, tanggal\_buka, tanggal\_tutup, kuota, biaya\_pendaftaran, status (draft/dibuka/ditutup/selesai)
- `spmb_jalur`: periode\_id, nama, kuota?, persyaratan? (j), is\_aktif
- `spmb_persyaratan_berkas`: periode\_id, nama, is\_wajib, urutan
- `spmb_kampanye`: cabang\_id?, nama, kanal\_ref (instagram/brosur/spanduk/event/referral/lainnya), tanggal\_mulai, tanggal\_akhir?, anggaran?, catatan?
- `spmb_kontak`: cabang\_id, sekolah\_id? (minat), tahun\_ajaran\_id, nama\_kontak, telepon, whatsapp?, email?, nama\_calon\_siswa?, jenjang\_minat\_id?->jenjang, sumber\_ref, kampanye\_id?, perujuk\_id?->orang, petugas\_id?->pegawai, status (baru/dihubungi/tertarik/mendaftar/tidak\_lanjut), alasan\_tidak\_lanjut?, kontak\_pertama\_pada, catatan?. Calon pelanggan (CP) sebelum mendaftar. Unik (cabang\_id, tahun\_ajaran\_id, telepon ternormalisasi, nama\_calon\_siswa tanpa beda huruf besar-kecil) mencegah duplikat tanpa menolak orang tua yang mendaftarkan dua anak.
- `spmb_tindak_lanjut`: kontak\_id, tanggal, kanal (whatsapp/telepon/kunjungan/email), petugas\_id->pegawai, hasil, catatan?, jadwal\_berikutnya?, status\_setelah
- `spmb_kegiatan`: cabang\_id, periode\_id?, nama, jenis (open\_house/school\_tour/webinar/lainnya), tanggal, lokasi?, kuota?
- `spmb_kegiatan_peserta`: kegiatan\_id, kontak\_id, status (daftar/hadir/tidak\_hadir)

### 9.2 Pendaftaran dan seleksi

- `spmb_pendaftar`: periode\_id, jalur\_id, cabang\_id, sekolah\_id, kontak\_id?, nomor\_pendaftaran (u), tanggal\_daftar, nama\_lengkap, jenis\_kelamin, tempat\_lahir, tanggal\_lahir, nik? (terenkripsi), nik\_hash? (unik per periode), nisn?, agama\_ref?, alamat?, wilayah\_id?, asal\_sekolah?, status (draft/terdaftar/diverifikasi/seleksi/lulus/cadangan/tidak\_lulus/daftar\_ulang/mundur/menjadi\_siswa), siswa\_id? (terisi saat konversi). Biodata sengaja berdiri sendiri; baris orang dan siswa baru dibuat saat konversi, sehingga pendaftar yang tidak jadi tidak mengotori data pokok.
- `spmb_pendaftar_wali`: pendaftar\_id, hubungan, nama, nik?, telepon?, pekerjaan\_ref?, penghasilan\_ref?, is\_kontak\_utama
- `spmb_berkas`: pendaftar\_id, persyaratan\_id->spmb\_persyaratan\_berkas, status\_verifikasi (menunggu/valid/tidak\_valid), catatan?, diverifikasi\_oleh?->users. File lewat lampiran.
- `spmb_tahap_seleksi`: periode\_id, nama (administrasi/tes\_akademik/observasi/wawancara/tes\_baca\_quran), urutan, bobot, nilai\_lulus?, ujian\_id?
- `spmb_nilai_seleksi`: pendaftar\_id, tahap\_id->spmb\_tahap\_seleksi, tanggal, nilai?, penguji\_id?->pegawai, catatan?; unik(pendaftar\_id, tahap\_id)
- `spmb_hasil`: pendaftar\_id (u), skor\_total?, peringkat?, keputusan (lulus/cadangan/tidak\_lulus), tanggal\_keputusan, ditetapkan\_oleh->users, catatan?
- `spmb_daftar_ulang`: pendaftar\_id (u), tanggal?, status (menunggu/selesai/batal), rombel\_id?
- Konversi pendaftar menjadi siswa adalah satu aksi service dalam satu transaksi: membuat orang, siswa, siswa\_sekolah, siswa\_wali, dan rombel\_siswa.
- Dashboard kontrol marketing dibuat sebagai view: v\_spmb\_corong (kontak, tindak lanjut, mendaftar, lulus, daftar ulang per cabang dan sumber), v\_spmb\_kinerja\_petugas, v\_spmb\_roi\_kampanye.

## 10. Administrasi, Arsip, dan Persuratan (F1)

### 10.1 Klasifikasi dan surat

- `klasifikasi_arsip`: induk\_id?->klasifikasi\_arsip, kode (u), nama, retensi\_aktif\_tahun?, retensi\_inaktif\_tahun?, tindakan\_akhir (musnah/permanen)
- `templat_surat`: kode (u), nama, jenis, isi (html dengan placeholder), variabel? (j), cabang\_id?, is\_aktif
- `surat_masuk`: cabang\_id, nomor\_agenda (u), nomor\_surat?, tanggal\_surat?, tanggal\_terima, pengirim, perihal, klasifikasi\_id?->klasifikasi\_arsip, sifat (biasa/penting/rahasia), ringkasan?, unit\_kerja\_id?, status (diterima/didisposisikan/diproses/selesai/diarsipkan). Scan lewat lampiran.
- `disposisi`: surat\_masuk\_id, induk\_id?->disposisi, dari\_user\_id->users, kepada\_user\_id->users, instruksi, batas\_waktu?, status (baru/dibaca/diproses/selesai), tanggapan?, dibaca\_pada?, selesai\_pada?
- `surat_keluar`: cabang\_id, nomor\_surat (u, dari penomoran), tanggal\_surat, tujuan, perihal, jenis (biasa/edaran/sk/mou/undangan/keterangan), klasifikasi\_id?->klasifikasi\_arsip, templat\_id?->templat\_surat, isi, pembuat\_id->pegawai, unit\_kerja\_id?, penandatangan\_id?->pegawai, status (draft/review/disetujui/terkirim/diarsipkan), tanggal\_kirim?. Persetujuan lewat approval.
- `surat_keluar_tujuan`: surat\_keluar\_id, pegawai\_id?, orang\_id?, nama\_tujuan?. Untuk SK atau surat massal.

### 10.2 Dokumen dan arsip

- `lokasi_arsip_fisik`: cabang\_id, induk\_id?->lokasi\_arsip\_fisik, kode, nama (ruang/lemari/rak/boks); unik(cabang\_id, kode)
- `dokumen`: cabang\_id? (kosong = yayasan), klasifikasi\_id?->klasifikasi\_arsip, nomor?, judul, deskripsi?, jenis\_ref (sk/mou/akta/izin/laporan/sertifikat/kontrak/lainnya), tanggal\_dokumen?, unit\_kerja\_id?, tingkat\_akses (publik/internal/terbatas/rahasia), status (aktif/inaktif/dimusnahkan), tanggal\_retensi\_habis?, lokasi\_fisik\_id?->lokasi\_arsip\_fisik, versi\_terkini, tag? (j) (s)
- `dokumen_versi`: dokumen\_id, versi, catatan?, dibuat\_oleh->users. File lewat lampiran.
- `dokumen_akses`: dokumen\_id, subjek\_type (user/role/unit\_kerja), subjek\_id, hak (lihat/ubah)

### 10.3 Knowledge management

- `kb_kategori`: induk\_id?->kb\_kategori, nama, urutan
- `kb_artikel`: kategori\_id->kb\_kategori, judul, slug (u), isi, penulis\_id->users, cakupan (yayasan/cabang), cabang\_id?, status (draft/terbit/arsip), versi, dipublikasi\_pada?, jumlah\_dilihat, tag? (j)
- `kb_artikel_revisi`: artikel\_id->kb\_artikel, versi, isi, dibuat\_oleh->users
- Pencarian penuh (dokumen, surat, artikel, soal, teks\_ekstrak pada lampiran) lewat indeks Meilisearch, bukan tabel tambahan.

## 11. Modul pendukung

Berikut usulan tambahan yang lazim dibutuhkan sekolah. Tidak perlu dikerjakan sebelum modul inti stabil.

### 11.1 Perpustakaan

- `koleksi_pustaka`: cabang\_id, judul, pengarang?, penerbit?, tahun\_terbit?, isbn?, kategori\_ref?, jenis (buku/majalah/digital), jumlah\_eksemplar
- `eksemplar_pustaka`: koleksi\_id, kode\_barcode (u), kondisi, status (tersedia/dipinjam/hilang/rusak), ruang\_id?
- `peminjaman_pustaka`: eksemplar\_id, peminjam\_id->orang, tanggal\_pinjam, tanggal\_jatuh\_tempo, tanggal\_kembali?, denda?, status

### 11.2 Kesehatan siswa (UKS)

- `pemeriksaan_kesehatan`: siswa\_id, tanggal, tinggi\_badan?, berat\_badan?, lingkar\_kepala?, penglihatan?, pendengaran?, gigi?, catatan?, pemeriksa?
- `kondisi_kesehatan_siswa`: siswa\_id, jenis (alergi/penyakit\_kronis/kebutuhan\_khusus), nama, tingkat?, penanganan?, obat? (kolom sensitif, enkripsi, akses terbatas)
- `kunjungan_uks`: cabang\_id, orang\_id, tanggal, keluhan, tindakan?, obat?, dijemput, petugas\_id?->pegawai

### 11.3 Komunikasi

- `pengumuman`: cabang\_id?, sekolah\_id?, judul, isi, terbit\_pada, kedaluwarsa\_pada?, penulis\_id->users, status
- `pengumuman_target`: pengumuman\_id, target\_type (semua/peran/sekolah/rombel), target\_id?
- `templat_pesan`: kode (u), kanal (whatsapp/email/push), isi, variabel? (j), is\_aktif
- `antrean_pesan`: cabang\_id?, kanal, tujuan, templat\_id?, isi, status (antri/terkirim/gagal), percobaan, dikirim\_pada?, respons? (j), konteks\_type?, konteks\_id?
- `buku_penghubung`: siswa\_id, pengirim\_id->users, arah (sekolah\_ke\_ortu/ortu\_ke\_sekolah), isi, dibaca\_pada?, induk\_id?->buku\_penghubung

### 11.4 Layanan internal

- `tiket_layanan`: cabang\_id, nomor (u), pelapor\_id->users, kategori (it/sarpras/keuangan/sdm/umum), judul, uraian, prioritas, status (baru/proses/selesai/ditutup), penanggung\_jawab\_id?->users, selesai\_pada?, nilai\_kepuasan?
- `tiket_balasan`: tiket\_id, user\_id, isi, is\_internal

### 11.5 Sengaja ditunda

Belum dirancang dan baru ditambah bila ada kebutuhan nyata: transportasi antar-jemput, kantin dan koperasi, asrama, manajemen kegiatan luar sekolah, manajemen vendor jasa.

## 12. Peta event lintas modul

Modul tidak saling query; mereka bereaksi terhadap event berikut.

- PembayaranBerhasil (Keuangan): alokasi ke tagihan, transaksi\_kas, jurnal; Komunikasi mengirim kuitansi lewat WhatsApp.
- PendaftarDikonversi (SPMB): Data Pokok membuat orang, siswa, wali, rombel\_siswa; Keuangan membuat tagihan daftar ulang.
- PenerimaanBarangDicatat (Inventaris): membuat aset\_unit atau menambah mutasi\_stok; Keuangan mencatat faktur dan hutang.
- PenjualanSelesai (Keuangan): Inventaris mengurangi stok lewat mutasi\_stok.
- AsetDihapus (Inventaris): Keuangan membuat jurnal pelepasan aset.
- PenyusutanDihitung (Inventaris): Keuangan membuat jurnal penyusutan.
- UjianSelesaiDinilai (CBT): Akademik mengisi nilai\_siswa.
- CutiDisetujui (Kepegawaian): absensi\_pegawai ditandai cuti pada rentang tanggal.
- PeriodeGajiDisetujui (Kepegawaian): Keuangan membuat jurnal dan pencairan.
- RaporTerbit (Akademik): Komunikasi memberi tahu orang tua.
- KenaikanKelasDitetapkan (Akademik): Data Pokok membentuk rombel\_siswa tahun berikutnya.
- ApprovalSelesai (Core, generik): modul pemilik subjek mengubah statusnya sendiri.

## 13. Urutan migrasi

- F0: wilayah, referensi, cabang, jenjang, sekolah, tingkat, unit\_kerja, tahun\_ajaran, semester, orang (+alamat, kontak darurat), users dan tabel permission, pengaturan, penomoran, lampiran, approval\_\*, siswa (+sekolah, wali), pegawai, jabatan, penugasan\_pegawai, rombel, rombel\_siswa.
- F1: Inventaris (tanah sampai penghapusan), SPMB, Arsip dan persuratan. Keuangan belum ada, jadi kolom yang menunjuk ke akun, jurnal, atau periode\_akuntansi dibiarkan tanpa FK dahulu.
- F2: Keuangan, lalu Kepegawaian. Setelah Keuangan ada, tambahkan FK yang tertunda dari F1.
- F3: Akademik dan Kesiswaan, lalu perpustakaan, UKS, komunikasi bila diperlukan.
- F4: LMS, bank soal, CBT.
- FK yang menunjuk ke modul yang dibangun belakangan (misalnya jadwal\_pelajaran.ruang\_id, tagihan.pendaftar\_id, ujian\_peserta.pendaftar\_id, aset\_unit ke akun) dipasang lewat migrasi tersendiri setelah tabel tujuannya ada.

## 14. Catatan implementasi

- Index: semua FK; (cabang\_id, tahun\_ajaran\_id) pada tabel besar; kolom pencarian seperti nik, nisn, nis\_yayasan, nip\_yayasan, dan semua nomor dokumen.
- Tabel yang tumbuh cepat: absensi\_log\_mentah, absensi\_pegawai, presensi\_siswa, mutasi\_stok, jurnal\_baris, ujian\_jawaban, antrean\_pesan, activity\_log. Rancang indeksnya sejak awal, siapkan kebijakan arsip data lama, dan pertimbangkan partisi per tahun bila sudah terasa lambat.
- Kolom terenkripsi (cast terenkripsi Laravel): nik, npwp, nomor\_rekening, ringkasan konseling, kondisi kesehatan, token ujian. Kolom terenkripsi tidak bisa dicari atau diurutkan; untuk nik simpan juga hash terpisah bila perlu pencarian.
- Snapshot yang tidak boleh berubah setelah terbit: rapor\_nilai, slip\_gaji\_rincian, saldo\_akun\_periode, jurnal berstatus posted.
- Uang selalu decimal. Operasi keuangan, stok, dan penomoran berjalan di dalam transaksi database dengan lockForUpdate.
- Pasang check dan unique di database, bukan hanya di validasi Laravel: jurnal\_baris (debit atau kredit nol), tagihan (siswa\_id atau pendaftar\_id), ujian\_peserta (siswa\_id atau pendaftar\_id), tanggal\_selesai tidak lebih awal dari tanggal\_mulai.
- Fitur PostgreSQL yang dipakai: unik parsial (tahun\_ajaran.is\_aktif, orang.nik, spmb\_kontak) dan NULLS NOT DISTINCT (PostgreSQL 15 ke atas) untuk akun dan pemetaan\_akun; kolom (j) bertipe jsonb dengan index GIN bila perlu dicari; exclusion constraint (ekstensi btree\_gist) untuk mencegah tumpang tindih reservasi\_ruang dan penugasan yang berlaku bersamaan; ekstensi pg\_trgm untuk pencarian nama parsial; migrasi transaksional; partisi deklaratif untuk tabel besar; dan row-level security sebagai lapis kedua isolasi cabang bila diperlukan. Aktifkan ekstensi di migrasi pertama.
- Total sekitar 265 tabel aplikasi, belum termasuk tabel bawaan Laravel dan package. Angka ini bukan target satu rilis: bangun per fase dan hanya migrasikan tabel fase yang sedang dikerjakan.

## 15. Keputusan dan hal yang masih terbuka

- Diputuskan: PostgreSQL sebagai mesin database.
- Diputuskan: LMS dan CBT dibangun sendiri karena banyak kebutuhan kustomisasi; bagian 6 berlaku penuh.
- Diputuskan: akuntansi dibangun sendiri dengan pembukuan per sekolah, per cabang, dan per yayasan beserta konsolidasi (bagian 7.1).
- Diputuskan: rapor dibuat fleksibel lewat templat per kurikulum dan jenjang (5.5), dengan tabel khusus penilaian deskriptif TK (5.7).
- Terbuka: contoh rapor nyata dari TK, SD, dan SMP serta kurikulum khusus yayasan, untuk menguji templat di 5.5 dan indikator di 5.7.
- Terbuka: bagan akun standar yayasan dan aturan konsolidasi (siapa membukukan biaya bersama cabang dan biaya pusat, kapan dialokasikan), perlu disepakati dengan bagian keuangan.
- Terbuka: aturan PPh21 dan BPJS untuk komponen\_gaji.
