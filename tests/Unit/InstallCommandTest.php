<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function installAnswers($command, array $answers)
{
    return $command
        ->expectsQuestion('Nama lengkap', $answers['name'])
        ->expectsQuestion('Email', $answers['email'])
        ->expectsQuestion('Username (3-50 karakter: huruf, angka, titik, garis bawah, strip)', $answers['username'])
        ->expectsQuestion('Kata sandi (minimal 10 karakter, huruf dan angka)', $answers['password'])
        ->expectsQuestion('Ulangi kata sandi', $answers['confirm']);
}

function installValid(array $extra = []): array
{
    return array_replace([
        'name' => 'Pengelola Uji',
        'email' => 'Pengelola@Example.com',
        'username' => 'Pengelola.Uji',
        'password' => 'KataSandi12345',
        'confirm' => 'KataSandi12345',
    ], $extra);
}

test('membuat super admin pertama yang langsung bisa dipakai', function () {
    installAnswers($this->artisan('app:install'), installValid())->assertSuccessful();

    $user = User::firstOrFail();

    expect($user->role)->toBe('super_admin')
        ->and($user->email)->toBe('pengelola@example.com')
        ->and($user->username)->toBe('pengelola.uji')
        ->and((bool) $user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('KataSandi12345', $user->password))->toBeTrue();
});

test('menolak bila super admin sudah ada', function () {
    User::factory()->createQuietly(['role' => 'super_admin']);

    $this->artisan('app:install')->assertFailed();

    expect(User::count())->toBe(1);
});

test('kata sandi lemah ditolak dan tidak ada akun dibuat', function () {
    installAnswers($this->artisan('app:install'), installValid([
        'password' => 'pendek1', 'confirm' => 'pendek1',
    ]))->assertFailed();

    expect(User::count())->toBe(0);
});

test('kata sandi tanpa angka ditolak', function () {
    installAnswers($this->artisan('app:install'), installValid([
        'password' => 'HurufSajaPanjang', 'confirm' => 'HurufSajaPanjang',
    ]))->assertFailed();

    expect(User::count())->toBe(0);
});

test('konfirmasi kata sandi harus sama', function () {
    installAnswers($this->artisan('app:install'), installValid([
        'confirm' => 'KataSandiLain12345',
    ]))->assertFailed();

    expect(User::count())->toBe(0);
});

test('email atau username yang sudah dipakai ditolak', function () {
    User::factory()->createQuietly([
        'role' => 'admin', 'email' => 'pengelola@example.com', 'username' => 'orang.lain',
    ]);

    installAnswers($this->artisan('app:install'), installValid())->assertFailed();

    expect(User::where('role', 'super_admin')->count())->toBe(0);
});
