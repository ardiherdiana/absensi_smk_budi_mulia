<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kunci Tanda Tangan Elektronik (TTE) — modul SPPD
    |--------------------------------------------------------------------------
    |
    | Rahasia untuk menandatangani (HMAC-SHA256) data pengajuan yang disetujui Kepala Sekolah.
    | Jika kosong, dipakai APP_KEY. Simpan hanya di server (.env) dan jangan diganti setelah ada
    | SPPD yang diterbitkan: mengganti kunci membuat seluruh TTE lama tidak lagi valid.
    |
    */

    'secret' => env('TTE_SECRET'),

];
