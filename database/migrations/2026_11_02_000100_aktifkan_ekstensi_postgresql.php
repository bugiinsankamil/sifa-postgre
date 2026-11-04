<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // btree_gist: exclusion constraint (mis. periode keanggotaan siswa tidak boleh tumpang tindih)
        // pg_trgm   : pencarian nama parsial (ILIKE '%budi%') memakai index
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Ekstensi sengaja dibiarkan: bisa dipakai objek lain di database yang sama.
    }
};
