@extends('spmb.layout')
@section('judul', 'Pendaftaran Murid Baru')
@section('isi')
    <h1>Pendaftaran Murid Baru</h1>
    <p class="redup">Pilih cabang dan jenjang yang dituju. Sudah mendaftar? <a href="{{ route('spmb.status') }}">Cek status pendaftaran</a>.</p>

    @forelse ($periode as $cabang => $daftar)
        <h2>{{ $cabang }}</h2>
        @foreach ($daftar as $p)
            <div class="kartu">
                <strong>{{ $p->sekolah }}</strong> ({{ $p->jenjang }})<br>
                <span class="redup">{{ $p->periode }} &middot; ditutup {{ \Illuminate\Support\Carbon::parse($p->tanggal_tutup)->format('d/m/Y') }}</span><br><br>
                <a class="tombol" href="{{ route('spmb.form', $p->id) }}">Daftar</a>
            </div>
        @endforeach
    @empty
        <div class="info">Saat ini belum ada pendaftaran yang dibuka.</div>
    @endforelse
@endsection
