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

class BillingCorrections
{
    public function __construct(private BillingLedger $ledger) {}

    public function voidReceipt(
        int $receiptId,
        User $user,
        string $reason
    ): void {
        $reason = $this->validReason($reason);

        DB::transaction(function () use ($receiptId, $user, $reason) {
            $customerId = (int) BillingReceipt::whereKey($receiptId)
                ->value('customer_id');

            if ($customerId === 0) {
                $this->fail('Penerimaan tidak ditemukan.');
            }

            Customer::withTrashed()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            $receipt = BillingReceipt::whereKey($receiptId)
                ->lockForUpdate()->firstOrFail();

            if ($receipt->status !== 'active') {
                $this->fail('Penerimaan ini sudah dibatalkan.');
            }

            $entries = BillingBalanceEntry::where('receipt_id', $receipt->id)
                ->where('type', 'in')
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()->get();

            foreach ($entries as $entry) {
                if ($this->ledger->sourceRemaining($entry) !== (int) $entry->amount) {
                    $this->fail(
                        'Saldo dari penerimaan ini sudah terpakai. '
                        .'Batalkan pemakaian saldo di menu Titip Saldo lebih dulu.'
                    );
                }
            }

            $allocations = BillingAllocation::where('receipt_id', $receipt->id)
                ->where('status', 'active')
                ->orderBy('invoice_id')
                ->lockForUpdate()->get();

            $invoices = Invoice::whereIn(
                'id',
                $allocations->pluck('invoice_id')->unique()->values()
            )->orderBy('id')->lockForUpdate()->get();

            $stamp = $this->stamp($user, $reason);

            foreach ($allocations as $allocation) {
                $allocation->update(['status' => 'void']);
            }

            foreach ($entries as $entry) {
                $entry->update($stamp);
            }

            $receipt->update($stamp);

            foreach ($invoices as $invoice) {
                $this->ledger->refreshInvoice($invoice);
            }
        }, 3);
    }

    public function voidBalanceUse(
        int $entryId,
        User $user,
        string $reason
    ): void {
        $reason = $this->validReason($reason);

        DB::transaction(function () use ($entryId, $user, $reason) {
            $customerId = (int) BillingBalanceEntry::whereKey($entryId)
                ->value('customer_id');

            if ($customerId === 0) {
                $this->fail('Pemakaian saldo tidak ditemukan.');
            }

            Customer::withTrashed()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            $entry = BillingBalanceEntry::whereKey($entryId)
                ->lockForUpdate()->firstOrFail();

            if (
                $entry->type !== 'use'
                || $entry->status !== 'active'
                || ! $entry->invoice_id
            ) {
                $this->fail('Pemakaian saldo ini tidak dapat dibatalkan.');
            }

            $invoice = Invoice::whereKey($entry->invoice_id)
                ->lockForUpdate()->firstOrFail();

            $entry->update($this->stamp($user, $reason));
            $this->ledger->refreshInvoice($invoice);
        }, 3);
    }

    private function stamp(User $user, string $reason): array
    {
        return [
            'status' => 'void',
            'voided_at' => now(),
            'voided_by' => $user->id,
            'voided_by_name' => mb_substr($user->name, 0, 255),
            'void_reason' => $reason,
        ];
    }

    private function validReason(string $reason): string
    {
        $data = Validator::make(
            ['reason' => trim($reason)],
            ['reason' => ['required', 'string', 'min:5', 'max:1000']]
        )->validate();

        return $data['reason'];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['billing' => $message]);
    }
}
