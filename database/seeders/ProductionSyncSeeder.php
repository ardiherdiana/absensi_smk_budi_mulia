<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes local data and reloads it verbatim from a phpMyAdmin dump of the
 * real production database (database/seeders/data/production.sql), so a
 * dev machine can work against the exact same guru/attendance/jadwal data
 * production has instead of the fake DatabaseSeeder roster.
 *
 * Deliberately NOT wired into DatabaseSeeder::run() - the two seeders'
 * usernames overlap (admin, kepsek, rio.falentino, ...) with different
 * ids/password hashes, so running both would just clobber each other.
 * Run this one explicitly and only this one:
 *
 *   php artisan db:seed --class=ProductionSyncSeeder
 *
 * To refresh with a newer export, overwrite database/seeders/data/production.sql
 * with a fresh phpMyAdmin dump (same table set) and re-run the command -
 * it always truncates first, so it's safe to run repeatedly.
 *
 * The dump predates two columns this app added after it was taken -
 * `jadwal_hari.jamMulaiAbsen` and `users.remember_token` - so this seeder
 * fills those in itself (see seedJadwalHari() and the plain INSERT for
 * users, which simply leaves remember_token at its nullable default).
 */
class ProductionSyncSeeder extends Seeder
{
    /** Tables present in the dump, in FK-safe (parent-first) order. Foreign
     * key checks are disabled for the whole run regardless, but keeping
     * this ordered means the same code still works if that ever changes. */
    private const TABLES_IN_ORDER = [
        'users',
        'guru',
        'settings',
        'holidays',
        'leave_requests',
        'attendance',
        'notifications',
        'push_subscriptions',
        'briefing_attendance',
    ];

    public function run(): void
    {
        $dumpPath = database_path('seeders/data/production.sql');
        abort_unless(is_file($dumpPath), 500, "Dump tidak ditemukan: {$dumpPath}");
        $sql = file_get_contents($dumpPath);

        Schema::disableForeignKeyConstraints();

        foreach ([...self::TABLES_IN_ORDER, 'jadwal_hari'] as $table) {
            DB::table($table)->truncate();
        }

        foreach (self::TABLES_IN_ORDER as $table) {
            $insert = $this->extractInsert($sql, $table);
            if ($insert === null) {
                continue;
            }

            if (in_array($table, ['guru', 'leave_requests'], true)) {
                $insert = str_replace('/api/uploads/', '/uploads/', $insert);
            }

            if ($table === 'users') {
                $insert = str_replace('$2b$', '$2y$', $insert);
            }

            DB::unprepared($insert);
        }

        $this->seedJadwalHari();
        $this->shiftUtcTimestampsToWib();

        Schema::enableForeignKeyConstraints();

        $this->command->info('Data lokal disinkronkan dari production.sql.');
    }

    /** Pulls out the single `INSERT INTO \`table\` (...) VALUES (...);`
     * statement for one table from the raw dump text, or null if that
     * table had no data at export time. */
    private function extractInsert(string $sql, string $table): ?string
    {
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'`.*?;/s';

        return preg_match($pattern, $sql, $matches) ? $matches[0] : null;
    }

    /** jadwal_hari's dump rows predate the jamMulaiAbsen column added in
     * migration 2026_09_13_012157 - hand-transcribed here (7 rows, checked
     * against the dump) with jamMulaiAbsen backfilled to equal that row's
     * own jamMasuk, the same convention that migration's own backfill used
     * for every environment that already had this table. */
    private function seedJadwalHari(): void
    {
        $rows = [
            ['hari' => 0, 'aktif' => false, 'jamMasuk' => '07:00', 'batasTelat' => '07:15', 'jamPulang' => '15:30', 'jamBriefing' => null, 'updatedAt' => '2026-09-12 05:06:42.492'],
            ['hari' => 1, 'aktif' => true, 'jamMasuk' => '12:30', 'batasTelat' => '13:00', 'jamPulang' => '17:00', 'jamBriefing' => '12:45', 'updatedAt' => '2026-09-12 08:49:34.281'],
            ['hari' => 2, 'aktif' => true, 'jamMasuk' => '12:30', 'batasTelat' => '13:00', 'jamPulang' => '17:00', 'jamBriefing' => null, 'updatedAt' => '2026-09-12 06:23:31.260'],
            ['hari' => 3, 'aktif' => true, 'jamMasuk' => '07:00', 'batasTelat' => '07:15', 'jamPulang' => '15:30', 'jamBriefing' => null, 'updatedAt' => '2026-09-11 09:25:49.916'],
            ['hari' => 4, 'aktif' => true, 'jamMasuk' => '07:00', 'batasTelat' => '07:15', 'jamPulang' => '15:30', 'jamBriefing' => null, 'updatedAt' => '2026-09-11 09:25:49.916'],
            ['hari' => 5, 'aktif' => true, 'jamMasuk' => '07:00', 'batasTelat' => '07:15', 'jamPulang' => '15:30', 'jamBriefing' => null, 'updatedAt' => '2026-09-11 09:25:44.135'],
            ['hari' => 6, 'aktif' => true, 'jamMasuk' => '12:40', 'batasTelat' => '13:00', 'jamPulang' => '17:00', 'jamBriefing' => null, 'updatedAt' => '2026-09-12 05:43:53.745'],
        ];

        foreach ($rows as $row) {
            DB::table('jadwal_hari')->insert([
                ...$row,
                'jamMulaiAbsen' => $row['jamMasuk'],
            ]);
        }
    }

    /** The old Node/Prisma backend always stores DateTime fields as UTC
     * (confirmed by reading js/backend/src/modules/attendance/attendance.service.ts:
     * it writes `new Date()` and, when serializing for its API, calls
     * `.toISOString()` - a UTC string - which the old React frontend then
     * rendered in the browser's local timezone). This app's `datetime`
     * columns carry no timezone marker of their own, and Carbon reads them
     * assuming they're already in config('app.timezone') (Asia/Jakarta) -
     * no conversion happens. So a UTC-written "10:33" reads back here as
     * "10:33 WIB" instead of the correct "17:33 WIB". Shift every real
     * moment-in-time column by +7h (WIB = UTC+7) once, right after import.
     * DATE_ADD on a NULL column safely stays NULL, so no extra guards are
     * needed. Pure calendar-date columns (tanggal, tanggalMulai,
     * tanggalSelesai) and the plain "HH:mm" strings in jadwal_hari carry no
     * such UTC/WIB ambiguity and are left untouched. */
    private function shiftUtcTimestampsToWib(): void
    {
        $columnsByTable = [
            'users' => ['createdAt', 'updatedAt'],
            'guru' => ['createdAt', 'updatedAt'],
            'settings' => ['updatedAt'],
            'holidays' => ['createdAt'],
            'leave_requests' => ['reviewedAt', 'createdAt', 'updatedAt'],
            'attendance' => ['jamMasuk', 'jamPulang', 'createdAt', 'updatedAt'],
            'notifications' => ['createdAt'],
            'push_subscriptions' => ['createdAt'],
            'briefing_attendance' => ['waktu', 'createdAt'],
            'jadwal_hari' => ['updatedAt'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            $assignments = collect($columns)
                ->map(fn ($col) => "`{$col}` = DATE_ADD(`{$col}`, INTERVAL 7 HOUR)")
                ->implode(', ');
            DB::statement("UPDATE `{$table}` SET {$assignments}");
        }
    }
}
