<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('akun baru mendapat role admin', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe('admin');
});

test('super admin memiliki akses pengelolaan', function () {
    $user = User::factory()->create();
    $user->role = 'super_admin';
    $user->save();

    expect(Gate::forUser($user)->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-system'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('access-billing'))->toBeTrue();
});

test('admin mendapat akses billing dan sistem, bukan kelola pengguna', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage-system'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('access-billing'))->toBeTrue();
});

test('server menolak admin pada route khusus super admin', function () {
    Route::middleware(['web', 'auth', 'can:manage-users'])
        ->get('/_test/billing-role', fn () => 'Diizinkan');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/_test/billing-role')
        ->assertForbidden();

    $user->role = 'super_admin';
    $user->save();

    $this->actingAs($user)
        ->get('/_test/billing-role')
        ->assertOk();
});
