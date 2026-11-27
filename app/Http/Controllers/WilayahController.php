<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WilayahController extends Controller
{
    public function index()
    {
        return view('wilayah.index', [
            'wilayah' => Wilayah::query()->with(['routers', 'users'])->orderBy('name')->get(),
            'customerCounts' => DB::table('customers')
                ->whereNotNull('wilayah_id')
                ->selectRaw('wilayah_id, COUNT(*) as total')
                ->groupBy('wilayah_id')
                ->pluck('total', 'wilayah_id'),
            'routers' => NetworkRouter::orderBy('name')->get(['id', 'name']),
            'admins' => User::where('role', 'admin')->orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $area = DB::transaction(function () use ($data) {
            $area = Wilayah::create([
                'name' => trim($data['name']),
                'code' => $data['code'],
            ]);
            $this->syncRelations($area, $data);

            return $area;
        });

        return redirect()->route('wilayah.index')
            ->with('status', 'Wilayah '.$area->name.' dibuat.');
    }

    public function update(Request $request, Wilayah $wilayah)
    {
        $data = $this->validated($request, $wilayah);

        DB::transaction(function () use ($data, $wilayah) {
            $wilayah->update([
                'name' => trim($data['name']),
                'code' => $data['code'],
            ]);
            $this->syncRelations($wilayah, $data);
        });

        return redirect()->route('wilayah.index')
            ->with('status', 'Wilayah '.$wilayah->name.' diperbarui.');
    }

    public function destroy(Wilayah $wilayah)
    {
        if (Wilayah::count() <= 1) {
            return redirect()->route('wilayah.index')
                ->withErrors(['wilayah' => 'Minimal harus ada satu wilayah.']);
        }

        $used = DB::table('customers')->where('wilayah_id', $wilayah->id)->exists()
            || DB::table('expenses')->where('wilayah_id', $wilayah->id)->exists();

        if ($used) {
            return redirect()->route('wilayah.index')->withErrors([
                'wilayah' => 'Wilayah masih dipakai pelanggan atau pengeluaran. Pindahkan datanya dulu.',
            ]);
        }

        $name = $wilayah->name;

        DB::transaction(function () use ($wilayah) {
            $wilayah->routers()->detach();
            $wilayah->users()->detach();
            $wilayah->delete();
        });

        return redirect()->route('wilayah.index')
            ->with('status', 'Wilayah '.$name.' dihapus.');
    }

    private function validated(Request $request, ?Wilayah $wilayah): array
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('wilayah', 'name')->ignore($wilayah?->id)],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('wilayah', 'code')->ignore($wilayah?->id)],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer', 'exists:network_routers,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'name.unique' => 'Nama wilayah sudah dipakai.',
            'code.unique' => 'Kode wilayah sudah dipakai.',
            'code.regex' => 'Kode hanya boleh huruf besar, angka, garis bawah, atau strip.',
        ], [
            'name' => 'nama',
            'code' => 'kode',
        ]);
    }

    private function syncRelations(Wilayah $wilayah, array $data): void
    {
        $wilayah->routers()->sync($data['router_ids'] ?? []);

        $adminIds = User::where('role', 'admin')
            ->whereIn('id', $data['user_ids'] ?? [])
            ->pluck('id')
            ->all();

        $wilayah->users()->sync($adminIds);
    }
}
