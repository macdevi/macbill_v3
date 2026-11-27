<?php

namespace App\Services;

use App\Models\BillingAllocation;
use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillingLedger
{
    public function balance(int $customerId): int
    {
        return (int) BillingBalanceEntry::where('customer_id', $customerId)
            ->where('status', 'active')
            ->sum('amount');
    }

    public function sourceRemaining(BillingBalanceEntry $source): int
    {
        $used = (int) BillingBalanceEntry::where('source_entry_id', $source->id)
            ->where('type', 'use')
            ->where('status', 'active')
            ->sum('amount');

        return (int) $source->amount + $used;
    }

    public function refreshInvoice(Invoice $invoice): void
    {
        $cash = (int) BillingAllocation::where('invoice_id', $invoice->id)
            ->where('status', 'active')->sum('amount');

        $credit = -(int) BillingBalanceEntry::where('invoice_id', $invoice->id)
            ->where('type', 'use')
            ->where('status', 'active')->sum('amount');

        $paid = $cash + $credit;

        if ($paid < 0 || $paid > (int) $invoice->total) {
            $this->fail('Alokasi invoice tidak konsisten. Transaksi dihentikan.');
        }

        if ($invoice->status === 'void' && $paid > 0) {
            $this->fail('Invoice dibatalkan tidak boleh memiliki pembayaran aktif.');
        }

        $status = $invoice->status === 'void'
            ? 'void'
            : ($paid >= (int) $invoice->total
                ? 'paid'
                : ($paid > 0 ? 'partial' : 'unpaid'));

        $invoice->forceFill([
            'paid_amount' => $paid,
            'status' => $status,
        ])->save();
    }

    public function receive(
        int $customerId,
        User $user,
        array $input,
        string $kind = 'payment'
    ): BillingReceipt {
        if (! in_array($kind, ['payment', 'deposit'], true)) {
            $this->fail('Jenis penerimaan tidak valid.');
        }

        $data = Validator::make($input, [
            'request_key' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'paid_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.InvoicePeriod::today()->format('Y-m-d'),
            ],
            'method' => ['required', 'in:cash,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
            'priority_ids' => ['nullable', 'array', 'max:200'],
            'priority_ids.*' => ['integer', 'distinct'],
            'confirmed' => ['required', 'accepted'],
        ])->validate();

        return DB::transaction(function () use (
            $customerId, $user, $data, $kind
        ) {
            Customer::withTrashed()->whereKey($customerId)
                ->lockForUpdate()->firstOrFail();

            if (BillingReceipt::where('request_key', $data['request_key'])->exists()) {
                $this->fail(
                    'Permintaan ini sudah diproses. Periksa riwayat sebelum mencatat lagi.'
                );
            }

            $open = $this->openInvoices($customerId);

            if ($kind === 'payment' && $open->isEmpty()) {
                $this->fail(
                    'Tidak ada invoice terbuka. Gunakan menu Titip Saldo.'
                );
            }

            $priority = array_map('intval', $data['priority_ids'] ?? []);
            $openIds = array_map('intval', $open->modelKeys());

            if ($kind === 'deposit' && $priority) {
                $this->fail('Setoran titip saldo tidak memakai pilihan invoice.');
            }

            if (array_diff($priority, $openIds)) {
                $this->fail(
                    'Pilihan invoice sudah berubah atau bukan milik pelanggan ini. Muat ulang halaman.'
                );
            }

            $receipt = BillingReceipt::create([
                'request_key' => $data['request_key'],
                'customer_id' => $customerId,
                'kind' => $kind,
                'amount' => (int) $data['amount'],
                'method' => $data['method'],
                'paid_on' => $data['paid_on'],
                'reference' => $data['reference'] ?? null,
                'status' => 'active',
                'recorded_by' => $user->id,
                'recorded_by_name' => mb_substr($user->name, 0, 255),
            ]);

            $remaining = (int) $data['amount'];

            if ($kind === 'payment') {
                $first = $open->filter(
                    fn ($invoice) => in_array((int) $invoice->id, $priority, true)
                );
                $rest = $open->reject(
                    fn ($invoice) => in_array((int) $invoice->id, $priority, true)
                );

                foreach ($first->concat($rest) as $invoice) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $this->refreshInvoice($invoice);
                    $due = (int) $invoice->total - (int) $invoice->paid_amount;
                    $take = min($due, $remaining);

                    if ($take <= 0) {
                        continue;
                    }

                    BillingAllocation::create([
                        'receipt_id' => $receipt->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $take,
                        'status' => 'active',
                    ]);

                    $this->refreshInvoice($invoice);
                    $remaining -= $take;
                }
            }

            if ($remaining > 0) {
                BillingBalanceEntry::create([
                    'customer_id' => $customerId,
                    'receipt_id' => $receipt->id,
                    'type' => 'in',
                    'amount' => $remaining,
                    'status' => 'active',
                    'note' => $kind === 'deposit'
                        ? 'Setoran titip saldo'
                        : 'Kelebihan pembayaran',
                    'created_by' => $user->id,
                    'created_by_name' => mb_substr($user->name, 0, 255),
                ]);
            }

            $receipt->forceFill([
                'receipt_snapshot' => ReceiptSnapshot::capture($receipt),
            ])->save();

            return $receipt;
        }, 3);
    }

    public function applyBalance(
        int $customerId,
        ?User $user = null
    ): int {
        return DB::transaction(function () use ($customerId, $user) {
            Customer::withTrashed()->whereKey($customerId)
                ->lockForUpdate()->firstOrFail();

            $sources = BillingBalanceEntry::where('customer_id', $customerId)
                ->where('type', 'in')
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()->get();

            $available = [];
            foreach ($sources as $source) {
                $remaining = $this->sourceRemaining($source);

                if ($remaining < 0) {
                    $this->fail('Sumber saldo negatif. Transaksi dihentikan.');
                }

                $available[$source->id] = $remaining;
            }

            if (array_sum($available) !== $this->balance($customerId)) {
                $this->fail('Mutasi saldo tidak konsisten. Transaksi dihentikan.');
            }

            $applied = 0;

            foreach ($this->openInvoices($customerId) as $invoice) {
                $this->refreshInvoice($invoice);
                $due = (int) $invoice->total - (int) $invoice->paid_amount;

                foreach ($sources as $source) {
                    if ($due <= 0) {
                        break;
                    }

                    $take = min($due, $available[$source->id]);

                    if ($take <= 0) {
                        continue;
                    }

                    BillingBalanceEntry::create([
                        'customer_id' => $customerId,
                        'invoice_id' => $invoice->id,
                        'source_entry_id' => $source->id,
                        'type' => 'use',
                        'amount' => -$take,
                        'status' => 'active',
                        'note' => 'Dipakai untuk '.$invoice->number,
                        'created_by' => $user?->id,
                        'created_by_name' => mb_substr(
                            $user?->name ?? 'Sistem', 0, 255
                        ),
                    ]);

                    $available[$source->id] -= $take;
                    $due -= $take;
                    $applied += $take;
                }

                $this->refreshInvoice($invoice);
            }

            return $applied;
        }, 3);
    }

    private function openInvoices(int $customerId)
    {
        return Invoice::where('customer_id', $customerId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->orderBy('issued_on')
            ->orderBy('id')
            ->lockForUpdate()->get();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'billing' => $message,
        ]);
    }
}
