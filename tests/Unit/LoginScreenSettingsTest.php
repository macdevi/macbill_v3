<?php

use App\Models\BillingSetting;
use App\Models\User;
use App\Services\LoginScreen;
use Illuminate\Support\Facades\DB;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function loginScreenPayload(array $extra = []): array
{
    return array_replace([
        'login_command' => '$ ./{slug} --masuk',
        'login_title' => '{nama} Billing',
        'login_tagline' => 'Tagihan Warga Net',
        'login_modules' => "Memuat modul A\nMemuat modul B",
        'login_spinner' => 'Menyiapkan layar',
        'login_message' => 'Silakan masuk.',
        'login_splash' => '1',
        'login_rain' => '0',
        'login_accent' => 'cyan',
    ], $extra);
}

beforeEach(function () {
    $this->lsSuper = User::factory()->createQuietly(['role' => 'super_admin']);
});

test('bawaan dipakai bila belum ada pengaturan', function () {
    $screen = LoginScreen::forLogin();

    expect($screen['tagline'])->toBe('Billing PPPoE & Tagihan Pelanggan')
        ->and($screen['modules'])->toBe(['Memuat modul pelanggan', 'Memuat modul tagihan'])
        ->and($screen['splash'])->toBeTrue()
        ->and($screen['fg'])->toBe('#33ff66');
});

test('super admin menyimpan tampilan login dan penanda terganti', function () {
    DB::table('billing_settings')->where('id', 1)->update(['business_name' => 'Net Warga']);

    $this->actingAs($this->lsSuper)
        ->put(route('billing-settings.login-screen'), loginScreenPayload())
        ->assertSessionHasNoErrors();

    $screen = LoginScreen::forLogin();

    expect($screen['command'])->toBe('$ ./net-warga --masuk')
        ->and($screen['title'])->toBe('Net Warga Billing')
        ->and($screen['tagline'])->toBe('Tagihan Warga Net')
        ->and($screen['modules'])->toBe(['Memuat modul A', 'Memuat modul B'])
        ->and($screen['splash'])->toBeTrue()
        ->and($screen['rain'])->toBeFalse()
        ->and($screen['fg'])->toBe('#5eead4');
});

test('halaman login menampilkan teks yang disimpan', function () {
    DB::table('billing_settings')->where('id', 1)->update(['login_screen' => json_encode([
        'tagline' => 'Tagihan Warga Net', 'splash' => false, 'rain' => false, 'accent' => 'amber',
    ])]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Tagihan Warga Net')
        ->assertSee('const SPLASH_ON = false;', false)
        ->assertSee('const RAIN_ON = false;', false)
        ->assertSee('--fg:#ffb000', false);
});

test('kembalikan bawaan menghapus pengaturan', function () {
    $this->actingAs($this->lsSuper)->put(route('billing-settings.login-screen'), loginScreenPayload());
    expect(BillingSetting::current()->getAttribute('login_screen'))->not->toBeNull();

    $this->actingAs($this->lsSuper)
        ->put(route('billing-settings.login-screen'), ['reset' => '1'])
        ->assertSessionHasNoErrors();

    expect(BillingSetting::current()->getAttribute('login_screen'))->toBeNull()
        ->and(LoginScreen::forLogin()['tagline'])->toBe('Billing PPPoE & Tagihan Pelanggan');
});

test('masukan tidak valid ditolak', function () {
    $cases = [
        ['login_accent' => 'ungu'],
        ['login_tagline' => "dua\nbaris"],
        ['login_title' => '{tidak_ada} judul'],
        ['login_modules' => "a\nb\nc\nd\ne\nf\ng"],
        ['login_modules' => str_repeat('x', 31)],
        ['login_modules' => "\n \n"],
        ['login_splash' => 'mungkin'],
    ];

    foreach ($cases as $override) {
        $this->actingAs($this->lsSuper)
            ->put(route('billing-settings.login-screen'), loginScreenPayload($override))
            ->assertSessionHasErrors();
    }

    expect(BillingSetting::current()->getAttribute('login_screen'))->toBeNull();
});

test('admin biasa tidak boleh mengubah tampilan login', function () {
    $admin = User::factory()->createQuietly(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('billing-settings.login-screen'), loginScreenPayload())
        ->assertForbidden();
});

test('menu tampilan login muncul di halaman pengaturan', function () {
    $this->actingAs($this->lsSuper)
        ->get(route('billing-settings.index'))
        ->assertOk()
        ->assertSee('Tampilan login')
        ->assertSee('Kembalikan bawaan');
});
