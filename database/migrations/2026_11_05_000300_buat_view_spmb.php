<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// View untuk dashboard kontrol marketing. View tidak melewati global scope Eloquent:
// saat dibaca, selalu saring dengan cabang_id yang boleh diakses pengguna.
return new class extends Migration
{
    public function up(): void
    {
        // Corong: kontak -> dihubungi -> mendaftar -> lulus -> daftar ulang. Hanya pendaftar yang berasal
        // dari kontak yang terhitung (pendaftar yang mendaftar langsung tanpa kontak tidak masuk corong ini).
        DB::statement(<<<'SQL'
CREATE VIEW v_spmb_corong AS
SELECT
    k.cabang_id,
    k.tahun_ajaran_id,
    k.sekolah_id,
    k.sumber_ref,
    COUNT(DISTINCT k.id) AS jumlah_kontak,
    COUNT(DISTINCT k.id) FILTER (WHERE k.status <> 'baru') AS dihubungi,
    COUNT(DISTINCT p.id) AS jumlah_pendaftar,
    COUNT(DISTINCT p.id) FILTER (WHERE h.keputusan = 'lulus') AS lulus,
    COUNT(DISTINCT p.id) FILTER (WHERE du.status = 'selesai') AS daftar_ulang
FROM spmb_kontak k
LEFT JOIN spmb_pendaftar p ON p.kontak_id = k.id
LEFT JOIN spmb_hasil h ON h.pendaftar_id = p.id
LEFT JOIN spmb_daftar_ulang du ON du.pendaftar_id = p.id
GROUP BY k.cabang_id, k.tahun_ajaran_id, k.sekolah_id, k.sumber_ref
SQL);

        DB::statement(<<<'SQL'
CREATE VIEW v_spmb_kinerja_petugas AS
SELECT
    k.cabang_id,
    k.tahun_ajaran_id,
    k.petugas_id,
    COUNT(DISTINCT k.id) AS jumlah_kontak,
    COUNT(DISTINCT t.id) AS jumlah_tindak_lanjut,
    COUNT(DISTINCT k.id) FILTER (WHERE k.status = 'mendaftar') AS jumlah_mendaftar
FROM spmb_kontak k
LEFT JOIN spmb_tindak_lanjut t ON t.kontak_id = k.id
WHERE k.petugas_id IS NOT NULL
GROUP BY k.cabang_id, k.tahun_ajaran_id, k.petugas_id
SQL);

        DB::statement(<<<'SQL'
CREATE VIEW v_spmb_roi_kampanye AS
SELECT
    c.id AS kampanye_id,
    c.cabang_id,
    c.nama,
    c.anggaran,
    COUNT(DISTINCT k.id) AS jumlah_kontak,
    COUNT(DISTINCT p.id) AS jumlah_pendaftar,
    COUNT(DISTINCT p.id) FILTER (WHERE du.status = 'selesai') AS jumlah_daftar_ulang,
    CASE
        WHEN COUNT(DISTINCT p.id) FILTER (WHERE du.status = 'selesai') > 0
        THEN c.anggaran / COUNT(DISTINCT p.id) FILTER (WHERE du.status = 'selesai')
    END AS biaya_per_siswa
FROM spmb_kampanye c
LEFT JOIN spmb_kontak k ON k.kampanye_id = c.id
LEFT JOIN spmb_pendaftar p ON p.kontak_id = k.id
LEFT JOIN spmb_daftar_ulang du ON du.pendaftar_id = p.id
GROUP BY c.id, c.cabang_id, c.nama, c.anggaran
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_spmb_roi_kampanye');
        DB::statement('DROP VIEW IF EXISTS v_spmb_kinerja_petugas');
        DB::statement('DROP VIEW IF EXISTS v_spmb_corong');
    }
};
