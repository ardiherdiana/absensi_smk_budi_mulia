<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** @var array<int, array{username: string, nama: string}> */
    private array $sampleGuru = [
        ['username' => 'rio.falentino', 'nama' => 'Rio Falentino, S.Pd. Gr.'],
        ['username' => 'doni.wijaya', 'nama' => 'Doni Wijaya, S.H. Gr.'],
        ['username' => 'marsono', 'nama' => 'Marsono, S.Kom., Gr.'],
        ['username' => 'wulan.endarwati', 'nama' => 'Wulan Endarwati, S.Pd. Gr.'],
        ['username' => 'sri.arum', 'nama' => 'Sri Arum Wulandari, M.Pd., Gr.'],
        ['username' => 'defriza', 'nama' => 'Defriza, S.Pd. Gr.'],
        ['username' => 'ika.candy', 'nama' => 'Ika Candy Permata, S.Pd. Gr.'],
        ['username' => 'bobi.royan', 'nama' => 'Bobi Royan Haryanto, S.Tr.Kom., Gr.'],
        ['username' => 'latif.abdul', 'nama' => 'Latif Abdul Salam, S.Tr.Kom., Gr.'],
        ['username' => 'feviana.miftahul', 'nama' => 'Feviana Miftahul N, S.Pd. Gr.'],
        ['username' => 'faradilla.ferhat', 'nama' => 'Faradilla Ferhat Arina Shandy, S.Kom., S.M., Gr.'],
        ['username' => 'dina.qoyimah', 'nama' => 'Dina Qoyimah, S.Pd. Gr.'],
        ['username' => 'ridho.robbi', 'nama' => 'Ridho Robbi, S.Kom., Gr.'],
        ['username' => 'herianto.saputra', 'nama' => 'Herianto Saputra, M.Kom., Gr.'],
        ['username' => 'mochammad.chafid', 'nama' => 'Mochammad Chafid Albiro, S.Pd.'],
        ['username' => 'irmawati', 'nama' => 'Irmawati, S.E.'],
        ['username' => 'tommy.ferdian', 'nama' => 'Tommy Ferdian Hadimarta, S.Kom., Gr.'],
        ['username' => 'eskiyana.khoiru', 'nama' => 'Eskiyana Khoiru Maisah, S.Pd.'],
        ['username' => 'dennies.maulana', 'nama' => 'Dennies Maulana Yusuf'],
        ['username' => 'muhammad.firdaus', 'nama' => 'Muhammad Firdaus Al-Husna, S.Kom.'],
        ['username' => 'rani.suryani', 'nama' => 'Rani Suryani'],
        ['username' => 'nita.ginatii', 'nama' => 'Nita Ginatii Fau, S.Psi., Gr.'],
        ['username' => 'muhammad.nur', 'nama' => 'Muhammad Nur Usman, S.Psi.'],
    ];

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        User::firstOrCreate(
            ['username' => 'admin'],
            ['password' => Hash::make('admin123'), 'role' => 'ADMIN']
        );

        $kepsekUser = User::firstOrCreate(
            ['username' => 'kepsek'],
            ['password' => Hash::make('kepsek123'), 'role' => 'KEPSEK']
        );
        Guru::firstOrCreate(
            ['userId' => $kepsekUser->id],
            ['nama' => 'Kepala Sekolah']
        );

        foreach ($this->sampleGuru as $g) {
            $user = User::firstOrCreate(
                ['username' => $g['username']],
                ['password' => Hash::make('guru123'), 'role' => 'GURU']
            );

            Guru::updateOrCreate(
                ['userId' => $user->id],
                ['nama' => $g['nama']]
            );
        }

        $this->command->info('Seed selesai.');
        $this->command->info('- 1 admin, 1 kepsek, '.count($this->sampleGuru).' guru');
        $this->command->info('Login admin  -> username: admin | password: admin123');
        $this->command->info('Login kepsek -> username: kepsek | password: kepsek123');
        $this->command->info('Login guru   -> password: guru123, username per orang:');
        foreach ($this->sampleGuru as $g) {
            $this->command->info('  - '.str_pad($g['username'], 20).' '.$g['nama']);
        }
    }
}
