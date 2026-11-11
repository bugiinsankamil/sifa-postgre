<?php

namespace App\Http\Requests;

use App\Models\SpmbPeriode;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DaftarSpmbRequest extends FormRequest
{
    private const HUBUNGAN = ['ayah', 'ibu', 'wali'];

    public function authorize(): bool
    {
        return true;
    }

    private function periode(): SpmbPeriode
    {
        return $this->route('periode');
    }

    private function persyaratan()
    {
        return DB::table('spmb_persyaratan_berkas')->where('periode_id', $this->periode()->id)->orderBy('urutan')->get();
    }

    public function rules(): array
    {
        $rules = [
            'situs' => ['nullable', 'max:0'],   // jebakan bot (honeypot): harus kosong
            'jalur_id' => ['required', Rule::exists('spmb_jalur', 'id')->where('periode_id', $this->periode()->id)->where('is_aktif', true)],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'nik' => ['nullable', 'digits:16'],
            'nisn' => ['nullable', 'digits:10'],
            'agama_ref' => ['nullable', Rule::exists('referensi', 'kode')->where('kategori', 'agama')],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'asal_sekolah' => ['nullable', 'string', 'max:255'],
            'kontak_utama' => ['required', Rule::in(self::HUBUNGAN)],
            'persetujuan' => ['accepted'],
        ];

        foreach (self::HUBUNGAN as $h) {
            $rules["wali.$h.nama"] = ['nullable', 'string', 'max:255'];
            $rules["wali.$h.nik"] = ['nullable', 'digits:16'];
            $rules["wali.$h.telepon"] = ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,20}$/'];
            $rules["wali.$h.pekerjaan_ref"] = ['nullable', Rule::exists('referensi', 'kode')->where('kategori', 'pekerjaan')];
            $rules["wali.$h.penghasilan_ref"] = ['nullable', Rule::exists('referensi', 'kode')->where('kategori', 'penghasilan')];
        }
        $rules['wali.wali.jenis_kelamin'] = ['nullable', Rule::in(['L', 'P'])];

        foreach ($this->persyaratan() as $syarat) {
            $rules["berkas.{$syarat->id}"] = [
                $syarat->is_wajib ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:'.config('spmb.berkas_maks_kb', 3072),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $wali = $this->input('wali', []);
            $terisi = fn (string $h) => filled($wali[$h]['nama'] ?? null);

            if (! collect(self::HUBUNGAN)->contains($terisi)) {
                $v->errors()->add('wali.ayah.nama', 'Isi data minimal satu orang tua atau wali.');

                return;
            }

            $utama = $this->input('kontak_utama');
            if (in_array($utama, self::HUBUNGAN, true)) {
                if (! $terisi($utama)) {
                    $v->errors()->add('kontak_utama', 'Kontak utama harus orang tua atau wali yang datanya diisi.');
                } elseif (! filled($wali[$utama]['telepon'] ?? null)) {
                    $v->errors()->add("wali.$utama.telepon", 'Nomor telepon/WhatsApp kontak utama wajib diisi.');
                }
            }

            if ($terisi('wali') && ! filled($wali['wali']['jenis_kelamin'] ?? null)) {
                $v->errors()->add('wali.wali.jenis_kelamin', 'Jenis kelamin wali wajib diisi.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'accepted' => 'Anda harus menyetujui penggunaan data untuk proses pendaftaran.',
            'digits' => ':attribute harus :digits digit angka.',
            'date' => ':attribute bukan tanggal yang valid.',
            'before' => ':attribute harus sebelum hari ini.',
            'in' => ':attribute tidak valid.',
            'exists' => ':attribute tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'file' => ':attribute harus berupa berkas.',
            'mimes' => ':attribute harus berupa berkas :values.',
            'max.string' => ':attribute maksimal :max karakter.',
            'max.file' => 'Ukuran :attribute maksimal :max KB.',
            'situs.max' => 'Permintaan tidak valid.',
        ];
    }

    public function attributes(): array
    {
        $atribut = [
            'jalur_id' => 'Jalur pendaftaran',
            'nama_lengkap' => 'Nama lengkap',
            'jenis_kelamin' => 'Jenis kelamin',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'nik' => 'NIK',
            'nisn' => 'NISN',
            'agama_ref' => 'Agama',
            'alamat' => 'Alamat',
            'asal_sekolah' => 'Asal sekolah',
            'kontak_utama' => 'Kontak utama',
        ];

        foreach (['ayah' => 'ayah', 'ibu' => 'ibu', 'wali' => 'wali'] as $h => $label) {
            $atribut["wali.$h.nama"] = "Nama $label";
            $atribut["wali.$h.nik"] = "NIK $label";
            $atribut["wali.$h.telepon"] = "Telepon $label";
            $atribut["wali.$h.pekerjaan_ref"] = "Pekerjaan $label";
            $atribut["wali.$h.penghasilan_ref"] = "Penghasilan $label";
        }
        $atribut['wali.wali.jenis_kelamin'] = 'Jenis kelamin wali';

        foreach ($this->persyaratan() as $syarat) {
            $atribut["berkas.{$syarat->id}"] = $syarat->nama;
        }

        return $atribut;
    }
}
