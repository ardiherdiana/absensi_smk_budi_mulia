<?php

namespace Database\Factories\Pguru;

use App\Models\Pguru\Supervisi;
use App\Models\User;
use App\Support\Pguru\SupervisiInstrumen;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supervisi> */
class SupervisiFactory extends Factory
{
    protected $model = Supervisi::class;

    public function definition(): array
    {
        return [
            'akunId' => User::factory(),
            'guruDinilai' => fake()->name(),
            'pemberiUmpanBalik' => fake()->name(),
            'tanggal' => '2026-09-24',
            'butir' => SupervisiInstrumen::lengkapiButir([]),
            'refleksi' => SupervisiInstrumen::lengkapiRefleksi([]),
        ];
    }
}
