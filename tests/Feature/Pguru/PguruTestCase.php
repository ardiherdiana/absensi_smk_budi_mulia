<?php

namespace Tests\Feature\Pguru;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class PguruTestCase extends TestCase
{
    protected const API = '/api/pguru';

    /**
     * Akun absensi (`users`) yang boleh memakai SIMAK: role GURU, aktif.
     *
     * @param  array<string, mixed>  $atribut
     */
    protected function akun(array $atribut = []): User
    {
        // `is_active` diisi eksplisit: pabrik tidak mengisinya, dan model yang baru dibuat belum membaca bawaan
        // kolom (true) dari basis data, sedangkan akun yang dimuat dari basis data selalu punya nilainya.
        return User::factory()->create($atribut + ['is_active' => true]);
    }

    /** Masuk sebagai akun tanpa melewati login (guard `pguru`, token Sanctum). */
    protected function sebagai(User $akun): static
    {
        Sanctum::actingAs($akun, ['*'], 'pguru');

        return $this;
    }

    protected function templateNilaiAsli(): string
    {
        return config('pguru.templates.nilai');
    }
}
