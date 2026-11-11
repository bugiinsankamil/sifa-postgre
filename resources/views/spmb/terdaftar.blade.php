@extends('spmb.layout')
@section('judul', 'Pendaftaran Diterima')
@section('isi')
    <h1>Pendaftaran diterima</h1>
    <div class="kartu">
        <p class="redup">Nomor pendaftaran Anda</p>
        <p class="nomor">{{ $nomor }}</p>
        <p>Simpan nomor ini. Petugas sekolah akan memverifikasi berkas dan menghubungi Anda melalui nomor telepon/WhatsApp kontak utama yang Anda isi.</p>
        <p>Untuk melihat perkembangan pendaftaran, buka <a href="{{ route('spmb.status') }}">halaman cek status</a> dan masukkan nomor pendaftaran beserta tanggal lahir calon siswa.</p>
    </div>
@endsection
