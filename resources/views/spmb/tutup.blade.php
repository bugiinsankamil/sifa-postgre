@extends('spmb.layout')
@section('judul', 'Pendaftaran Tidak Dibuka')
@section('isi')
    <h1>{{ $info->jenjang }} - {{ $info->cabang }}</h1>
    <div class="peringatan">Pendaftaran pada periode ini sedang tidak dibuka.</div>
    <p><a href="{{ route('spmb.index') }}">Lihat pendaftaran yang sedang dibuka</a></p>
@endsection
