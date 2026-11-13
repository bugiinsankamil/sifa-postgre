@extends('spmb.layout')
@section('judul', 'Cek Status Pendaftaran')
@section('isi')
    <h1>Cek status pendaftaran</h1>

    @if (session('sukses'))
        <div class="info">{{ session('sukses') }}</div>
    @endif
    @if ($errors->has('umum') || $errors->has('berkas'))
        <div class="peringatan">{{ $errors->first('umum') ?: $errors->first('berkas') }}</div>
    @endif

    <form method="POST" action="{{ route('spmb.status.cek') }}" class="kartu">
        @csrf
        <div class="grid">
            <div>
                <label for="nomor_pendaftaran">Nomor pendaftaran</label>
                <input type="text" id="nomor_pendaftaran" name="nomor_pendaftaran" value="{{ old('nomor_pendaftaran') }}" required>
                @error('nomor_pendaftaran') <small class="galat">{{ $message }}</small> @enderror
            </div>
            <div>
                <label for="tanggal_lahir">Tanggal lahir calon siswa</label>
                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required>
                @error('tanggal_lahir') <small class="galat">{{ $message }}</small> @enderror
            </div>
        </div>
        <p><button type="submit">Cek status</button></p>
    </form>

    @if ($dicari && $pendaftar === null)
        <div class="peringatan">Data tidak ditemukan. Periksa nomor pendaftaran dan tanggal lahir.</div>
    @endif

    @if ($pendaftar !== null)
        <div class="kartu">
            <p class="redup">{{ $pendaftar->nomor_pendaftaran }}</p>
            <p><strong>{{ $pendaftar->nama_lengkap }}</strong></p>
            <p>{{ $keterangan }}</p>

            @if ($berkas->isNotEmpty())
                <h2>Berkas</h2>
                @foreach ($berkas as $b)
                    <div style="margin-bottom:12px">
                        <p style="margin:0">
                            {{ $b->nama }}:
                            @if ($b->status_verifikasi === 'valid') Sudah valid
                            @elseif ($b->status_verifikasi === 'tidak_valid') <strong>Perlu diperbaiki</strong>
                                @if ($b->catatan) ({{ $b->catatan }}) @endif
                            @else Menunggu verifikasi
                            @endif
                        </p>

                        @if ($b->status_verifikasi === 'tidak_valid' && ($bisaUnggah ?? false))
                            <form method="POST" action="{{ route('spmb.berkas.unggah', $b->id) }}" enctype="multipart/form-data" style="margin-top:6px">
                                @csrf
                                <label for="berkas_{{ $b->id }}">Berkas perbaikan (JPG, PNG, atau PDF)</label>
                                <input type="file" id="berkas_{{ $b->id }}" name="berkas" accept=".jpg,.jpeg,.png,.pdf" required>
                                <p><button type="submit">Unggah perbaikan</button></p>
                            </form>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    @endif
@endsection
