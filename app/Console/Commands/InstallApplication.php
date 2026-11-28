<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InstallApplication extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Membuat super admin pertama. Hanya berjalan bila belum ada super admin.';

    public function handle(): int
    {
        if (User::query()->where('role', 'super_admin')->exists()) {
            $this->error('Super admin sudah ada. Gunakan menu Pengguna untuk menambah akun.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nama lengkap'));
        $email = Str::lower(trim((string) $this->ask('Email')));
        $username = Str::lower(trim((string) $this->ask(
            'Username (3-50 karakter: huruf, angka, titik, garis bawah, strip)'
        )));
        $password = (string) $this->secret('Kata sandi (minimal 10 karakter, huruf dan angka)');
        $confirmation = (string) $this->secret('Ulangi kata sandi');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers(), 'confirmed'],
        ]);

        $validator->after(function ($v) use ($email, $username) {
            if ($email !== '' && User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                $v->errors()->add('email', 'Email sudah dipakai akun lain. Untuk menaikkan akun yang ada, gunakan app:make-super-admin.');
            }

            if ($username !== '' && User::query()->whereRaw('LOWER(username) = ?', [$username])->exists()) {
                $v->errors()->add('username', 'Username sudah dipakai akun lain.');
            }
        });

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            $this->line('Tidak ada akun yang dibuat.');

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($name, $email, $username, $password) {
            if (User::query()->where('role', 'super_admin')->lockForUpdate()->exists()) {
                return false;
            }

            (new User)->forceFill([
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'role' => 'super_admin',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();

            return true;
        });

        if (! $created) {
            $this->error('Super admin sudah dibuat oleh proses lain. Tidak ada perubahan.');

            return self::FAILURE;
        }

        $this->info('Berhasil. Super admin dibuat.');
        $this->line('Login memakai username ATAU email: '.$username.' / '.$email);

        return self::SUCCESS;
    }
}
