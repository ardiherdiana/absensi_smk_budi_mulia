<?php

namespace Database\Factories\Pguru;

use App\Models\Pguru\Kelas;
use App\Models\Pguru\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Siswa> */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'kelasId' => Kelas::factory(),
            'nomor' => fake()->numberBetween(1, 60000),
            'nama' => fake()->name(),
        ];
    }
}
