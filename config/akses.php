<?php

return [
    // Peran yang boleh masuk panel admin (Filament). orang_tua, siswa, dan guru tidak termasuk:
    // mereka akan memakai portal tersendiri.
    'peran_panel' => [
        'sysadmin', 'yayasan', 'direktur', 'kepala_cabang', 'kepala_sekolah',
        'wakil_kepala_sekolah', 'pj_program', 'admin', 'staf_servis',
    ],
];
