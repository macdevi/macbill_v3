<?php

namespace App\Models\Scopes;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class CustomerOwnedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (WilayahScope::allowedIds() === null) {
            return;
        }

        static $hasColumn = [];
        $table = $model->getTable();
        $hasColumn[$table] ??= Schema::hasColumn($table, 'customer_id');

        if (!$hasColumn[$table]) {
            return;
        }

        $builder->whereIn(
            $model->qualifyColumn('customer_id'),
            Customer::withTrashed()->select('customers.id')
        );
    }
}
