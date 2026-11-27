<?php

namespace App\Services;

use App\Models\BillingAllocation;
use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ReceiptSnapshot
{
    private static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private static function baseValues(BillingReceipt $receipt): array
    {
        $receipt->loadMissing('customer');

        return [
            '{nomor_nota}' => 'NOTA-'.str_pad(
                (string) $receipt->id, 8, '0', STR_PAD_LEFT
            ),
            '{tanggal}' => $receipt->paid_on->format('d/m/Y'),
            '{nama_pelanggan}' => (string) ($receipt->customer?->name ?? '-'),
            '{kode_pelanggan}' => (string) ($receipt->customer?->code ?? '-'),
            '{jumlah_diterima}' => self::rupiah((int) $receipt->amount),
            '{metode}' => $receipt->method === 'cash'
                ? 'Tunai' : 'Transfer bank',
            '{referensi}' => $receipt->reference ?: '-',
            '{dicatat_oleh}' => (string) $receipt->recorded_by_name,
        ];
    }

    public static function capture(BillingReceipt $receipt): array
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new \LogicException(
                'Snapshot nota harus dibuat dalam transaksi penerimaan.'
            );
        }

        if ($receipt->status !== 'active' || $receipt->receipt_snapshot !== null) {
            throw new \LogicException(
                'Snapshot hanya dibuat sekali untuk penerimaan aktif baru.'
            );
        }

        $allocations = BillingAllocation::with('invoice')
            ->where('receipt_id', $receipt->id)
            ->where('status', 'active')
            ->orderBy('id')->get();

        $lines = [];
        $details = [];

        foreach ($allocations as $allocation) {
            $invoice = $allocation->invoice;

            if (! $invoice) {
                throw new \RuntimeException(
                    'Invoice alokasi tidak ditemukan saat membuat snapshot.'
                );
            }

            $left = (int) $invoice->total - (int) $invoice->paid_amount;

            if ($left < 0) {
                throw new \RuntimeException('Sisa invoice negatif.');
            }

            $lines[] = $invoice->number.' ('
                .$invoice->period_start->format('d/m/Y').' - '
                .$invoice->period_end->format('d/m/Y').')';
            $lines[] = '  Dibayar '.self::rupiah((int) $allocation->amount)
                .($left > 0 ? ', sisa '.self::rupiah($left) : ' (lunas)');

            $details[] = [
                'invoice_id' => $invoice->id,
                'number' => $invoice->number,
                'period_start' => $invoice->period_start->format('Y-m-d'),
                'period_end' => $invoice->period_end->format('Y-m-d'),
                'allocated' => (int) $allocation->amount,
                'remaining' => $left,
            ];
        }

        $outstanding = (int) Invoice::where(
            'customer_id', $receipt->customer_id
        )->whereIn('status', ['unpaid', 'partial'])
            ->sum(DB::raw('total - paid_amount'));

        $added = (int) BillingBalanceEntry::where('receipt_id', $receipt->id)
            ->where('type', 'in')->where('status', 'active')->sum('amount');

        $balance = (int) BillingBalanceEntry::where(
            'customer_id', $receipt->customer_id
        )->where('status', 'active')->sum('amount');

        if ($outstanding < 0 || $added < 0 || $balance < 0) {
            throw new \RuntimeException(
                'Nilai tagihan atau saldo negatif saat membuat snapshot.'
            );
        }

        if ((int) $allocations->sum('amount') + $added !== (int) $receipt->amount) {
            throw new \RuntimeException(
                'Jumlah alokasi dan tambahan saldo tidak cocok dengan penerimaan.'
            );
        }

        $values = array_merge(self::baseValues($receipt), [
            '{rincian_invoice}' => implode("\n", $lines) ?: '-',
            '{sisa_tagihan}' => $outstanding > 0
                ? self::rupiah($outstanding) : '',
            '{titip_saldo}' => $added > 0 ? self::rupiah($added) : '',
            '{saldo_titip}' => $balance > 0 ? self::rupiah($balance) : '',
        ]);

        return [
            'version' => 1,
            'captured_at' => now()->toIso8601String(),
            'customer_id' => (int) $receipt->customer_id,
            'kind' => $receipt->kind,
            'amount' => (int) $receipt->amount,
            'allocations' => $details,
            'outstanding' => $outstanding,
            'credit_added' => $added,
            'credit_balance' => $balance,
            'values' => $values,
        ];
    }

    public static function legacyValues(BillingReceipt $receipt): array
    {
        $lines = [];

        foreach (
            BillingAllocation::with('invoice')
                ->where('receipt_id', $receipt->id)
                ->orderBy('id')->get() as $allocation
        ) {
            $invoice = $allocation->invoice;
            $lines[] = $invoice
                ? $invoice->number.' ('
                    .$invoice->period_start->format('d/m/Y').' - '
                    .$invoice->period_end->format('d/m/Y').')'
                : 'Invoice #'.$allocation->invoice_id;
            $lines[] = '  Dibayar '.self::rupiah((int) $allocation->amount);
        }

        $added = (int) BillingBalanceEntry::where('receipt_id', $receipt->id)
            ->where('type', 'in')->sum('amount');

        return array_merge(self::baseValues($receipt), [
            '{rincian_invoice}' => implode("\n", $lines) ?: '-',
            '{sisa_tagihan}' => '',
            '{titip_saldo}' => $added > 0 ? self::rupiah($added) : '',
            '{saldo_titip}' => '',
        ]);
    }
}
