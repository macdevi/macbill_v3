<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InternetPackage;
use Illuminate\Support\Facades\DB;

class PackageCustomerGuard
{
    public function errors(
        InternetPackage $package,
        array $data,
        array $sync
    ): array {
        if (! $package->exists) {
            return [];
        }

        $usedRouterIds = Customer::where('internet_package_id', $package->id)
            ->distinct()->pluck('network_router_id')->all();

        if ($usedRouterIds === []) {
            return [];
        }

        $oldProfiles = DB::table('internet_package_router')
            ->where('internet_package_id', $package->id)
            ->pluck('normal_profile', 'network_router_id')->all();

        return self::check(
            $package->service_type,
            $data['service_type'],
            $usedRouterIds,
            $oldProfiles,
            $sync
        );
    }

    public static function check(
        string $oldType,
        string $newType,
        array $usedRouterIds,
        array $oldProfiles,
        array $newMappings
    ): array {
        if ($usedRouterIds === []) {
            return [];
        }

        if ($oldType !== $newType) {
            return [
                'service_type' => 'Jenis layanan belum dapat diganti karena paket masih dipakai pelanggan. Buat paket lain lalu pindahkan pelanggan melalui alur yang sesuai.',
            ];
        }

        if ($newType !== 'pppoe') {
            return [];
        }

        foreach (array_unique($usedRouterIds) as $routerId) {
            if (! array_key_exists($routerId, $newMappings)) {
                return [
                    'mappings' => 'Pemetaan router belum dapat dilepas karena masih ada pelanggan paket ini pada router tersebut.',
                ];
            }

            $oldProfile = $oldProfiles[$routerId] ?? null;
            $newProfile = $newMappings[$routerId]['normal_profile'] ?? null;

            if ($oldProfile !== null && $oldProfile !== $newProfile) {
                return [
                    'mappings' => 'Profil normal pada router ini masih dipakai sebagai pemetaan paket pelanggan. Perubahan profil memerlukan alur pemindahan dengan preview, bukan langsung melalui edit paket.',
                ];
            }
        }

        return [];
    }
}
