<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin';

    protected $description = 'Menetapkan super admin dari lingkungan lokal';

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('Perintah ini hanya diizinkan pada lingkungan lokal.');
            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask(
            'Email akun yang akan menjadi super admin'
        )));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            $this->error('Akun tidak ditemukan. Tidak ada perubahan.');
            return self::FAILURE;
        }

        $this->table(
            ['Nama', 'Username', 'Email', 'Role sekarang'],
            [[$user->name, $user->username, $user->email, $user->role]]
        );

        if ($user->role === 'super_admin') {
            $this->info('Akun ini sudah menjadi super admin.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Jadikan akun tersebut super admin?', false)) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        $user->role = 'super_admin';
        $user->save();

        $this->info('Berhasil. Akun tersebut sekarang super admin.');

        return self::SUCCESS;
    }
}
