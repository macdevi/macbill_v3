<?php

namespace App\Http\Controllers;

use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\BillingSetting;
use App\Services\BillingCorrections;
use App\Services\BillingLedger;
use App\Services\ReceiptText;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, BillingLedger $ledger)
    {
        $request->validate(['customer_id' => ['required', 'integer', 'exists:customers,id']]);

        $receipt = $ledger->receive(
            (int) $request->input('customer_id'),
            $request->user(),
            $request->only(['request_key', 'amount', 'paid_on', 'method', 'reference', 'priority_ids', 'confirmed'])
        );

        $excess = (int) BillingBalanceEntry::where('receipt_id', $receipt->id)
            ->where('type', 'in')->sum('amount');
        $message = 'Pembayaran Rp'.number_format($receipt->amount, 0, ',', '.').' dicatat: Rp'
            .number_format($receipt->amount - $excess, 0, ',', '.').' ke invoice';
        if ($excess > 0) {
            $message .= ', Rp'.number_format($excess, 0, ',', '.').' menjadi titip saldo';
        }

        return back()
            ->with('status', $message.'.')
            ->with('nota_receipt', $receipt->id);
    }

    public function nota(BillingReceipt $receipt)
    {
        $built = ReceiptText::build(
            $receipt,
            BillingSetting::current()->receipt_template
        );

        return response()->json($built)->header('Cache-Control', 'private, no-store');
    }

    public function void(Request $request, BillingReceipt $receipt, BillingCorrections $corrections)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        $corrections->voidReceipt($receipt->id, $request->user(), $data['reason']);

        return back()->with('status', 'Penerimaan dibatalkan. Riwayatnya tetap tersimpan.');
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }
}
