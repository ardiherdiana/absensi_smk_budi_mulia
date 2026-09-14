<?php

namespace App\Services;

use App\Models\Settings;

// Mirrors backend/src/modules/settings/settings.service.ts exactly.
class SettingsService
{
    public function getSettings(): Settings
    {
        // Matches the "namaSekolah" column's DB default explicitly - unlike
        // Prisma's create(), Eloquent's create() doesn't reload DB-applied
        // defaults into the returned instance on its own.
        return Settings::find(1) ?? Settings::create(['id' => 1, 'namaSekolah' => 'SMK Budi Mulia Karawang']);
    }

    public function updateSettings(array $input): Settings
    {
        $settings = $this->getSettings();
        $settings->update($input);

        return $settings;
    }
}
