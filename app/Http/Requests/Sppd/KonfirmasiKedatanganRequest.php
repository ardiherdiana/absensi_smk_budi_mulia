<?php

namespace App\Http\Requests\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Foundation\Http\FormRequest;

class KonfirmasiKedatanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('konfirmasiKedatangan', $this->route('pengajuan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PengajuanSppd $pengajuan */
        $pengajuan = $this->route('pengajuan');

        return [
            'pejabat_nama' => ['required', 'string', 'max:120'],
            'pejabat_jabatan' => ['required', 'string', 'max:120'],
            'tiba_tanggal' => ['required', 'date', 'after_or_equal:'.$pengajuan->tanggal_berangkat->toDateString()],
            'berangkat_tanggal' => ['required', 'date', 'after_or_equal:tiba_tanggal'],
            'bukti' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tiba_tanggal.after_or_equal' => 'Tanggal tiba tidak boleh sebelum tanggal berangkat.',
            'berangkat_tanggal.after_or_equal' => 'Tanggal berangkat dari tujuan tidak boleh sebelum tanggal tiba.',
        ];
    }
}
