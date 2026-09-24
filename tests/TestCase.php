<?php

namespace Tests;

use App\Models\Guru;
use App\Models\JadwalHari;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Freezes "now" to a fixed point in time for deterministic attendance-window tests. */
    protected function freezeAt(string $dateTime): Carbon
    {
        $now = Carbon::parse($dateTime);
        Carbon::setTestNow($now);

        return $now;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function setJadwalHari(int $hari, array $attributes = []): JadwalHari
    {
        return JadwalHari::updateOrCreate(['hari' => $hari], array_merge([
            'aktif' => true,
            'jamMulaiAbsen' => '07:00',
            'jamMasuk' => '07:00',
            'batasTelat' => '07:15',
            'jamPulang' => '15:30',
        ], $attributes));
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = User::factory()->admin()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    protected function actingAsGuru(array $guruAttributes = [], array $userAttributes = []): Guru
    {
        $guru = Guru::factory()->create([
            'userId' => User::factory()->create($userAttributes),
            ...$guruAttributes,
        ]);
        $this->actingAs($guru->user);

        return $guru;
    }

    protected function actingAsKepsek(array $guruAttributes = []): Guru
    {
        $guru = Guru::factory()->create([
            'userId' => User::factory()->kepsek(),
            ...$guruAttributes,
        ]);
        $this->actingAs($guru->user);

        return $guru;
    }
}
