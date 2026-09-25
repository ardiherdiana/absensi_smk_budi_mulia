<?php

namespace App\Http\Controllers\Pguru;

use App\Http\Controllers\Controller;
use App\Models\Pguru\Kelas;
use App\Models\User;
use Illuminate\Http\Request;

abstract class PguruController extends Controller
{
    /** Akun yang sedang masuk (`users`, guard `pguru`). Semua data SIMAK terikat ke `users.id` lewat kolom `akunId`. */
    protected function akun(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * Validasi dengan pesan berbahasa Indonesia. Pesan bawaan Laravel berbahasa Inggris dan absensi
     * tidak punya lang/id, jadi pesan modul ini didefinisikan di sini saja (tidak mengubah absensi).
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $atribut  nama kolom yang ramah pengguna
     * @return array<string, mixed>
     */
    protected function validasi(Request $request, array $rules, array $atribut = []): array
    {
        return $request->validate($rules, [
            'required' => ':attribute wajib diisi',
            'string' => ':attribute harus berupa teks',
            'email' => 'Format email tidak valid',
            'unique' => ':attribute sudah terdaftar',
            // Kunci inline tidak mengenal jenis (max.string); yang bukan teks ditimpa per atribut di bawah.
            'max' => ':attribute maksimal :max karakter',
            'min' => ':attribute minimal :min karakter',
            'nilai.min' => ':attribute minimal :min isian',
            'nilai.max' => ':attribute terlalu banyak isian',
            'butir.max' => ':attribute terlalu banyak isian',
            'refleksi.max' => ':attribute terlalu banyak isian',
            'berkas.max' => ':attribute maksimal :max KB',
            'numeric' => ':attribute harus berupa angka',
            'integer' => ':attribute harus berupa bilangan bulat',
            'between' => ':attribute harus antara :min dan :max',
            'digits' => ':attribute harus :digits digit',
            'regex' => 'Format :attribute tidak valid',
            'in' => ':attribute tidak valid',
            'date_format' => 'Format :attribute tidak valid',
            'boolean' => ':attribute harus benar atau salah',
            'array' => ':attribute tidak valid',
            'file' => ':attribute harus berupa berkas',
            'mimes' => ':attribute harus berformat :values',
        ], $atribut);
    }

    protected function kelasMilik(Request $request, string $id): Kelas
    {
        return Kelas::where('akunId', $this->akun($request)->id)->find($id)
            ?? abort(404, 'Kelas tidak ditemukan');
    }
}
