<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class WilayahScope implements Scope
{
    public static function enforced(): bool
    {
        return (bool) config('wilayah.enforce', false);
    }

    /**
     * Daftar ID wilayah yang boleh diakses. Null berarti tanpa batas
     * (saklar mati, super admin, atau tidak ada pengguna login).
     *
     * @return array<int, int>|null
     */
    public static function allowedIds(): ?array
    {
        if (!self::enforced()) {
            return null;
        }

        $user = Auth::user();
        if (!$user || $user->isSuperAdmin()) {
            return null;
        }

        static $memo = [];

        return $memo[$user->id] ??= ($user->accessibleWilayahIds() ?? []);
    }

    public function apply(Builder $builder, Model $model): void
    {
        $ids = self::allowedIds();

        if ($ids === null) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('wilayah_id'), $ids);
    }
}
