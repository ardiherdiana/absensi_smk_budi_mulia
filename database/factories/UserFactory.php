<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'role' => 'GURU',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'ADMIN']);
    }

    public function kepsek(): static
    {
        return $this->state(['role' => 'KEPSEK']);
    }
}
