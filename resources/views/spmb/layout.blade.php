<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'SPMB') | Sekolah Islam Terpadu Fathona</title>
    <style>
        :root { --hijau: #1b6b4a; --garis: #d5dbd8; --teks: #1f2933; --redup: #5f6b66; --latar: #f4f7f5; --galat: #b42318; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--teks); background: var(--latar); line-height: 1.5; }
        .kepala { background: var(--hijau); color: #fff; padding: 14px 16px; font-weight: 600; }
        .wadah { max-width: 760px; margin: 0 auto; padding: 16px 16px 48px; }
        h1 { font-size: 1.4rem; margin: 8px 0 4px; }
        h2 { font-size: 1.05rem; margin: 28px 0 8px; padding-bottom: 6px; border-bottom: 2px solid var(--hijau); }
        .redup { color: var(--redup); font-size: .92rem; }
        .kartu { background: #fff; border: 1px solid var(--garis); border-radius: 10px; padding: 14px 16px; margin: 10px 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        label { display: block; font-size: .9rem; font-weight: 600; margin-bottom: 4px; }
        input[type=text], input[type=date], input[type=tel], input[type=file], select, textarea {
            width: 100%; padding: 10px; font-size: 1rem; border: 1px solid var(--garis); border-radius: 8px; background: #fff; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--hijau); outline-offset: 1px; }
        .pilihan { font-weight: 400; display: flex; gap: 8px; align-items: center; }
        .galat { color: var(--galat); font-size: .85rem; display: block; margin-top: 3px; }
        .peringatan { background: #fdecea; border: 1px solid #f4b4ae; color: var(--galat); border-radius: 8px; padding: 10px 14px; margin: 12px 0; }
        .info { background: #e9f5ef; border: 1px solid #b7dcc7; border-radius: 8px; padding: 10px 14px; margin: 12px 0; }
        button, .tombol { display: inline-block; background: var(--hijau); color: #fff; border: 0; border-radius: 8px; padding: 12px 22px; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .nomor { font-size: 1.6rem; font-weight: 700; letter-spacing: .5px; }
        .jebakan { position: absolute; left: -9999px; height: 0; overflow: hidden; }
        a { color: var(--hijau); }
    </style>
</head>
<body>
    <div class="kepala">Sekolah Islam Terpadu Fathona &middot; Penerimaan Murid Baru</div>
    <main class="wadah">
        @yield('isi')
    </main>
</body>
</html>
