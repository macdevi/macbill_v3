<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerChange;
use App\Services\CustomerAuditValues;

class CustomerAuditObserver
{
    private \WeakMap $before;

    public function __construct()
    {
        $this->before = new \WeakMap;
    }

    public function updating(Customer $customer): void
    {
        $keys = array_intersect(
            array_keys(CustomerAuditValues::FIELDS),
            array_keys($customer->getDirty())
        );

        if ($keys === []) {
            return;
        }

        // Membaca hanya kolom yang boleh masuk audit, tanpa kredensial.
        $persisted = $customer->newModelQuery()
            ->whereKey($customer->getKey())
            ->lockForUpdate()
            ->firstOrFail(array_keys(CustomerAuditValues::FIELDS));

        $this->before[$customer] = $persisted->getRawOriginal();
    }

    public function created(Customer $customer): void
    {
        $changes = CustomerAuditValues::diff(
            [],
            $customer->getAttributes(),
            array_keys(CustomerAuditValues::FIELDS)
        );

        $this->record($customer, 'created', $changes);
    }

    public function updated(Customer $customer): void
    {
        if (! isset($this->before[$customer])) {
            return;
        }

        $before = $this->before[$customer];
        unset($this->before[$customer]);

        $changes = CustomerAuditValues::diff(
            $before,
            $customer->getAttributes(),
            array_keys($customer->getChanges())
        );

        if ($changes !== []) {
            $this->record($customer, 'updated', $changes);
        }
    }

    private function record(Customer $customer, string $event, array $changes): void
    {
        $actor = auth()->user();
        $source = app()->runningInConsole()
            ? 'console'
            : (request()->route()?->getName() ?? 'web');

        CustomerChange::create([
            'customer_id' => $customer->getKey(),
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => mb_substr(
                (string) ($actor?->name ?? 'Sistem'), 0, 255
            ),
            'event' => $event,
            'source' => mb_substr($source, 0, 120),
            'changes' => $changes,
        ]);
    }
}
