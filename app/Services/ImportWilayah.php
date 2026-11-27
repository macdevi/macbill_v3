<?php

namespace App\Services;

use App\Models\NetworkRouter;
use App\Models\Scopes\WilayahScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ImportWilayah
{
    /** @return array<int, int> */
    public static function candidates(int $routerId): array
    {
        $served = DB::table('network_router_wilayah')
            ->where('network_router_id', $routerId)
            ->pluck('wilayah_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowed = WilayahScope::allowedIds();

        return $allowed === null
            ? $served
            : array_values(array_intersect($served, $allowed));
    }

    public static function resolve(?int $requested, int $routerId): ?int
    {
        $candidates = self::candidates($routerId);

        if ($requested !== null) {
            if (!in_array($requested, $candidates, true)) {
                throw new \DomainException('Wilayah tidak sesuai dengan router atau di luar akses Anda. Import dibatalkan.');
            }

            return $requested;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($candidates === [] && !WilayahScope::enforced()) {
            return null;
        }

        throw new \DomainException(
            count($candidates) > 1
                ? 'Router melayani lebih dari satu wilayah. Pilih wilayah import. Import dibatalkan.'
                : 'Router tidak melayani wilayah yang dapat Anda akses. Import dibatalkan.'
        );
    }

    public static function routers(): Builder
    {
        $query = NetworkRouter::query()->orderBy('name');
        $allowed = WilayahScope::allowedIds();

        if ($allowed !== null) {
            $query->whereIn(
                'id',
                DB::table('network_router_wilayah')
                    ->whereIn('wilayah_id', $allowed)
                    ->select('network_router_id')
            );
        }

        return $query;
    }
}
