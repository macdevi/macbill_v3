<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SetupLoginAccount extends Command
{
    protected $signature = 'app:setup-login';

    protected $description = 'Membuat akun lokal atau menambahkan username ke akun yang sudah ada';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Perintah ini hanya untuk pengembangan lokal.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask('Email akun')));
        $username = strtolower(trim((string) $this->ask(
            'Username, 3-50 karakter: huruf, angka, titik, garis bawah, atau tanda minus'
        )));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $validator = Validator::make(
            compact('email', 'username'),
            [
                'email' => ['required', 'email', 'max:255'],
                'username' => [
                    'required',
                    'string',
                    'min:3',
                    'max:50',
                    'regex:/^[a-z0-9][a-z0-9._-]*$/',
                ],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $duplicate = User::whereRaw('LOWER(username) = ?', [$username])
            ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if ($duplicate) {
            $this->error('Username sudah digunakan akun lain.');

            return self::FAILURE;
        }

        if ($user && ! $this->confirm(
            'Akun email tersebut ditemukan. Tambahkan/ganti username akun ini?',
            false
        )) {
            return self::FAILURE;
        }

        $name = $user?->name
            ?? trim((string) $this->ask('Nama pengelola'));

        $changePassword = ! $user || $this->confirm(
            'Ganti password akun ini juga?',
            false
        );

        $password = null;

        if ($changePassword) {
            $password = (string) $this->secret('Password baru, minimal 6 karakter');
            $confirmation = (string) $this->secret('Ulangi password');

            $validator = Validator::make([
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
        }

        $isNew = ! $user;
        $user ??= new User;

        $user->name = $name;
        $user->email = $email;
        $user->username = $username;

        if ($changePassword) {
            $user->password = Hash::make($password);
            $user->remember_token = null;
        }

        if ($isNew) {
            $user->email_verified_at = now();
        }

        $user->save();

        $this->info('Berhasil. Login dapat memakai username ATAU email.');
        $this->line('Username: '.$user->username);
        $this->line('Email: '.$user->email);

        return self::SUCCESS;
    }
}
