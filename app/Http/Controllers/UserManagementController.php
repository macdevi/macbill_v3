<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'wilayahList' => \App\Models\Wilayah::orderBy('name')->get(['id', 'name']),
            'users' => User::query()
                ->with('wilayah')
                ->orderByRaw("CASE WHEN role = 'super_admin' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $user = new User;
        DB::transaction(function () use ($user, $data) {
            $user->forceFill([
                'name' => trim($data['name']),
                'username' => Str::lower(trim($data['username'])),
                'email' => Str::lower(trim($data['email'])),
                'role' => $data['role'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
            $this->syncWilayah($user, $data);
        });

        return redirect()->route('users.index')
            ->with('status', 'Akun '.$user->name.' berhasil dibuat.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $isSelf = (int) $user->id === (int) $request->user()->id;
        $revoke = false;

        DB::transaction(function () use ($data, $user, $isSelf, &$revoke) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($data['role'] !== $user->role) {
                if ($isSelf) {
                    throw ValidationException::withMessages([
                        'role' => 'Anda tidak dapat mengubah peran akun sendiri.',
                    ]);
                }
                if ($user->role === 'super_admin' && $this->otherActiveSuperAdmins($user) === 0) {
                    throw ValidationException::withMessages([
                        'role' => 'Super admin aktif terakhir tidak boleh diturunkan perannya.',
                    ]);
                }
                $revoke = true;
            }

            $user->forceFill([
                'name' => trim($data['name']),
                'username' => Str::lower(trim($data['username'])),
                'email' => Str::lower(trim($data['email'])),
                'role' => $data['role'],
            ]);

            if (!empty($data['password'])) {
                $user->forceFill([
                    'password' => $data['password'],
                    'remember_token' => Str::random(60),
                ]);
                $revoke = true;
            }

            $user->save();
            $this->syncWilayah($user, $data);

            if ($revoke) {
                $this->revokeSessions($user, $isSelf ? session()->getId() : null);
            }
        });

        return redirect()->route('users.index')
            ->with('status', 'Akun '.$user->name.' diperbarui.');
    }

    public function toggle(Request $request, User $user)
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('users.index')
                ->withErrors(['toggle' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $error = null;
        $active = false;

        DB::transaction(function () use ($user, &$error, &$active) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $isActive = (bool) ($user->is_active ?? true);

            if ($isActive && $user->role === 'super_admin' && $this->otherActiveSuperAdmins($user) === 0) {
                $error = 'Super admin aktif terakhir tidak boleh dinonaktifkan.';
                return;
            }

            $user->forceFill([
                'is_active' => !$isActive,
                'remember_token' => Str::random(60),
            ])->save();

            if ($isActive) {
                $this->revokeSessions($user, null);
            }

            $active = !$isActive;
        });

        if ($error) {
            return redirect()->route('users.index')->withErrors(['toggle' => $error]);
        }

        return redirect()->route('users.index')->with(
            'status',
            'Akun '.$user->name.($active ? ' diaktifkan.' : ' dinonaktifkan.')
        );
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('users.index')
                ->withErrors(['delete' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        $name = $user->name;
        $error = null;

        try {
            DB::transaction(function () use ($user, &$error) {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($locked->role !== 'admin') {
                    $error = 'Hanya akun Admin yang dapat dihapus. Super admin cukup dinonaktifkan.';
                    return;
                }

                $this->revokeSessions($locked, null);
                $locked->wilayah()->detach();
                $locked->delete();
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            $error = 'Akun ini masih dirujuk data lain sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        if ($error) {
            return redirect()->route('users.index')->withErrors(['delete' => $error]);
        }

        return redirect()->route('users.index')
            ->with('status', 'Akun '.$name.' dihapus. Riwayat transaksinya tetap tersimpan.');
    }

    private function validated(Request $request, ?User $user): array
    {
        $id = $user?->id;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'in:super_admin,admin'],
            'wilayah_ids' => ['nullable', 'array', 'max:100'],
            'wilayah_ids.*' => ['integer', 'distinct', 'exists:wilayah,id'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                Password::min(10)->letters()->numbers(),
            ],
        ], [
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, atau strip (tanpa @ dan spasi).',
            'role.in' => 'Peran tidak valid.',
            'wilayah_ids.*.exists' => 'Wilayah yang dipilih tidak valid.',
            'wilayah_ids.*.integer' => 'Wilayah yang dipilih tidak valid.',
        ], [
            'name' => 'nama',
            'username' => 'username',
            'email' => 'email',
            'role' => 'peran',
            'password' => 'password',
        ]);

        $validator->after(function ($v) use ($request, $id) {
            if ($request->input('role') === 'admin' && empty($request->input('wilayah_ids'))) {
                $v->errors()->add('wilayah_ids', 'Admin harus ditugaskan ke minimal satu wilayah.');
            }

            $username = Str::lower(trim((string) $request->input('username')));
            $email = Str::lower(trim((string) $request->input('email')));

            $taken = fn (string $column, string $value) => User::query()
                ->whereRaw('LOWER('.$column.') = ?', [$value])
                ->when($id, fn ($q) => $q->where('id', '!=', $id))
                ->exists();

            if ($username !== '' && $taken('username', $username)) {
                $v->errors()->add('username', 'Username sudah dipakai akun lain.');
            }
            if ($email !== '' && $taken('email', $email)) {
                $v->errors()->add('email', 'Email sudah dipakai akun lain.');
            }
        });

        return $validator->validate();
    }

    private function syncWilayah(User $user, array $data): void
    {
        $ids = $user->role === 'admin'
            ? array_values(array_unique(array_map('intval', $data['wilayah_ids'] ?? [])))
            : [];

        $user->wilayah()->sync($ids);
    }

    private function otherActiveSuperAdmins(User $user): int
    {
        return User::query()
            ->where('role', 'super_admin')
            ->where('id', '!=', $user->id)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->count();
    }

    private function revokeSessions(User $user, ?string $keepSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
