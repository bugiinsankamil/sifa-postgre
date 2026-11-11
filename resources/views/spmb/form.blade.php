@extends('spmb.layout')
@section('judul', 'Formulir Pendaftaran')
@section('isi')
    @php
        $labelWali = ['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali (isi bila bukan ayah/ibu)'];
    @endphp

    <h1>Pendaftaran {{ $info->jenjang }}</h1>
    <p class="redup">{{ $info->sekolah }} &middot; {{ $periode->nama }} &middot; ditutup {{ $periode->tanggal_tutup->format('d/m/Y') }}</p>

    @error('umum') <div class="peringatan">{{ $message }}</div> @enderror
    @if ($errors->any() && ! $errors->has('umum'))
        <div class="peringatan">Ada isian yang belum benar. Periksa kolom yang ditandai merah di bawah.</div>
    @endif

    <form method="POST" action="{{ route('spmb.store', $periode->id) }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="jebakan" aria-hidden="true">
            <label>Situs web <input type="text" name="situs" tabindex="-1" autocomplete="off"></label>
        </div>

        <h2>Jalur pendaftaran</h2>
        <div>
            <label for="jalur_id">Jalur</label>
            <select id="jalur_id" name="jalur_id" required>
                <option value="">Pilih jalur</option>
                @foreach ($jalur as $j)
                    <option value="{{ $j->id }}" @selected((string) old('jalur_id') === (string) $j->id)>{{ $j->nama }}</option>
                @endforeach
            </select>
            @error('jalur_id') <small class="galat">{{ $message }}</small> @enderror
        </div>

        <h2>Data calon siswa</h2>
        <div class="grid">
            <div>
                <label for="nama_lengkap">Nama lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required>
                @error('nama_lengkap') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="jenis_kelamin">Jenis kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin" required>
                    <option value="">Pilih</option>
                    <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                </select>
                @error('jenis_kelamin') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="tempat_lahir">Tempat lahir</label>
                <input type="text" id="tempat_lahir" name="tempat_lahir" value="{{ old('tempat_lahir') }}" required>
                @error('tempat_lahir') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="tanggal_lahir">Tanggal lahir</label>
                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required>
                @error('tanggal_lahir') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="nik">NIK (16 digit, bila ada)</label>
                <input type="text" inputmode="numeric" id="nik" name="nik" value="{{ old('nik') }}" maxlength="16">
                @error('nik') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="nisn">NISN (10 digit, bila ada)</label>
                <input type="text" inputmode="numeric" id="nisn" name="nisn" value="{{ old('nisn') }}" maxlength="10">
                @error('nisn') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="agama_ref">Agama</label>
                <select id="agama_ref" name="agama_ref">
                    <option value="">Pilih</option>
                    @foreach ($agama as $kode => $label)
                        <option value="{{ $kode }}" @selected(old('agama_ref') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('agama_ref') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="asal_sekolah">Asal sekolah/TK</label>
                <input type="text" id="asal_sekolah" name="asal_sekolah" value="{{ old('asal_sekolah') }}">
                @error('asal_sekolah') <small class="galat">{{ $message }}</small> @enderror
            </div>
        </div>
        <div style="margin-top:12px">
            <label for="alamat">Alamat tempat tinggal</label>
            <textarea id="alamat" name="alamat" rows="3">{{ old('alamat') }}</textarea>
            @error('alamat') <small class="galat">{{ $message }}</small> @enderror
        </div>

        <h2>Data orang tua / wali</h2>
        <p class="redup">Isi minimal satu. Tandai satu orang sebagai kontak utama; sekolah akan menghubungi nomor tersebut.</p>

        @foreach ($labelWali as $k => $label)
            <div class="kartu">
                <strong>{{ $label }}</strong>
                <div class="grid" style="margin-top:10px">
                    <div>
                        <label for="wali_{{ $k }}_nama">Nama lengkap</label>
                        <input type="text" id="wali_{{ $k }}_nama" name="wali[{{ $k }}][nama]" value="{{ old("wali.$k.nama") }}">
                        @error("wali.$k.nama") <small class="galat">{{ $message }}</small> @enderror
                    </div>
                    @if ($k === 'wali')
                        <div>
                            <label for="wali_wali_jenis_kelamin">Jenis kelamin</label>
                            <select id="wali_wali_jenis_kelamin" name="wali[wali][jenis_kelamin]">
                                <option value="">Pilih</option>
                                <option value="L" @selected(old('wali.wali.jenis_kelamin') === 'L')>Laki-laki</option>
                                <option value="P" @selected(old('wali.wali.jenis_kelamin') === 'P')>Perempuan</option>
                            </select>
                            @error('wali.wali.jenis_kelamin') <small class="galat">{{ $message }}</small> @enderror
                        </div>
                    @endif
                    <div>
                        <label for="wali_{{ $k }}_nik">NIK (bila ada)</label>
                        <input type="text" inputmode="numeric" id="wali_{{ $k }}_nik" name="wali[{{ $k }}][nik]" value="{{ old("wali.$k.nik") }}" maxlength="16">
                        @error("wali.$k.nik") <small class="galat">{{ $message }}</small> @enderror
                    </div>
                    <div>
                        <label for="wali_{{ $k }}_telepon">Telepon / WhatsApp</label>
                        <input type="tel" id="wali_{{ $k }}_telepon" name="wali[{{ $k }}][telepon]" value="{{ old("wali.$k.telepon") }}">
                        @error("wali.$k.telepon") <small class="galat">{{ $message }}</small> @enderror
                    </div>
                    <div>
                        <label for="wali_{{ $k }}_pekerjaan">Pekerjaan</label>
                        <select id="wali_{{ $k }}_pekerjaan" name="wali[{{ $k }}][pekerjaan_ref]">
                            <option value="">Pilih</option>
                            @foreach ($pekerjaan as $kode => $teks)
                                <option value="{{ $kode }}" @selected(old("wali.$k.pekerjaan_ref") === $kode)>{{ $teks }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="wali_{{ $k }}_penghasilan">Penghasilan per bulan</label>
                        <select id="wali_{{ $k }}_penghasilan" name="wali[{{ $k }}][penghasilan_ref]">
                            <option value="">Pilih</option>
                            @foreach ($penghasilan as $kode => $teks)
                                <option value="{{ $kode }}" @selected(old("wali.$k.penghasilan_ref") === $kode)>{{ $teks }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="pilihan" style="margin-top:10px">
                    <input type="radio" name="kontak_utama" value="{{ $k }}" @checked(old('kontak_utama', 'ibu') === $k)>
                    Jadikan kontak utama
                </label>
            </div>
        @endforeach
        @error('kontak_utama') <small class="galat">{{ $message }}</small> @enderror

        @if ($persyaratan->isNotEmpty())
            <h2>Berkas persyaratan</h2>
            <p class="redup">Format JPG, PNG, atau PDF. Maksimal {{ number_format(config('spmb.berkas_maks_kb', 3072) / 1024, 0) }} MB per berkas.</p>
            @foreach ($persyaratan as $s)
                <div class="kartu">
                    <label for="berkas_{{ $s->id }}">{{ $s->nama }} @if ($s->is_wajib) <span class="galat" style="display:inline">(wajib)</span> @else <span class="redup">(opsional)</span> @endif</label>
                    <input type="file" id="berkas_{{ $s->id }}" name="berkas[{{ $s->id }}]" accept=".jpg,.jpeg,.png,.pdf">
                    @error('berkas.'.$s->id) <small class="galat">{{ $message }}</small> @enderror
                </div>
            @endforeach
        @endif

        <div class="kartu">
            <label class="pilihan">
                <input type="checkbox" name="persetujuan" value="1" @checked(old('persetujuan'))>
                Saya menyatakan data yang diisi benar dan menyetujui sekolah memakainya untuk proses penerimaan murid baru.
            </label>
            @error('persetujuan') <small class="galat">{{ $message }}</small> @enderror
        </div>

        <p><button type="submit">Kirim pendaftaran</button></p>
    </form>
@endsection
