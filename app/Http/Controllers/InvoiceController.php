<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceGenerator;
use App\Services\InvoicePeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:open,unpaid,partial,paid,void'],
        ]);

        $query = Invoice::query()->orderByDesc('issued_on')->orderByDesc('id')
            ->with([
                'customer:id,whatsapp',
                'allocations.receipt',
                'creditUses',
            ]);
        if (filled($filters['q'] ?? null)) {
            $q = trim($filters['q']);
            $query->where(fn ($b) => $b
                ->where('number', 'like', '%'.$q.'%')
                ->orWhere('customer_name', 'like', '%'.$q.'%')
                ->orWhere('customer_code', 'like', '%'.$q.'%'));
        }
        $status = array_key_exists('status', $filters)
            ? (string) ($filters['status'] ?? '')
            : 'open';

        if ($status === 'open') {
            $query->whereIn('status', ['unpaid', 'partial']);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $today = InvoicePeriod::today();
        $monthRange = [$today->startOfMonth()->format('Y-m-d'), $today->endOfMonth()->format('Y-m-d')];

        $summary = [
            'unpaid_count' => Invoice::whereIn('status', ['unpaid', 'partial'])->count(),
            'unpaid_total' => (int) Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - paid_amount')),
            'month_count' => Invoice::where('status', '!=', 'void')->whereBetween('issued_on', $monthRange)->count(),
            'month_total' => (int) Invoice::where('status', '!=', 'void')->whereBetween('issued_on', $monthRange)->sum('total'),
        ];

        return $this->page('index', [
            'invoices' => $invoices = $query->paginate(20)->withQueryString(),
            'summary' => $summary,
            'waTemplate' => \App\Models\BillingSetting::current()->wa_template,
            'payKey' => (string) \Illuminate\Support\Str::uuid(),
            'statusFilter' => $status,
            'openByCustomer' => Invoice::whereIn('customer_id', $invoices->pluck('customer_id')->unique())
                ->whereIn('status', ['unpaid', 'partial'])
                ->orderBy('issued_on')->orderBy('id')
                ->get(['id', 'customer_id', 'number', 'total', 'paid_amount'])
                ->groupBy('customer_id')
                ->map(fn ($g) => $g->map(fn ($i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'due' => (int) $i->total - (int) $i->paid_amount,
                ])->values()),
        ]);
    }

    public function paymentHistory(Request $request, Invoice $invoice)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $receipts = \App\Models\BillingReceipt::query()
            ->where('customer_id', $invoice->customer_id);

        $uses = \App\Models\BillingBalanceEntry::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'use');

        $receiptRows = $receipts->select('id')
            ->selectRaw("'receipt' as kind, paid_on as happened_at");
        $useRows = $uses->select('id')
            ->selectRaw("'use' as kind, created_at as happened_at");

        $rows = DB::query()
            ->fromSub($receiptRows->unionAll($useRows), 'payment_history')
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->orderBy('kind')
            ->paginate(10)
            ->withQueryString();

        $receiptIds = $rows->getCollection()
            ->where('kind', 'receipt')->pluck('id');
        $useIds = $rows->getCollection()
            ->where('kind', 'use')->pluck('id');

        $receiptMap = \App\Models\BillingReceipt::query()
            ->where('customer_id', $invoice->customer_id)
            ->whereIn('id', $receiptIds)
            ->with('allocations.invoice:id,number')
            ->get()->keyBy('id');

        $useMap = \App\Models\BillingBalanceEntry::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'use')
            ->whereIn('id', $useIds)
            ->with('invoice:id,number')
            ->get()->keyBy('id');

        $html = view('invoices._history_items', compact(
            'invoice', 'rows', 'receiptMap', 'useMap'
        ))->render();

        return response()->json([
            'html' => $html,
            'customer' => $invoice->customer_name,
            'invoice' => $invoice->number,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function invoiceNotes(Request $request, Invoice $invoice)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $receipts = \App\Models\BillingReceipt::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'active')
            ->whereHas('allocations', function ($query) use ($invoice) {
                $query->where('invoice_id', $invoice->id)
                    ->where('status', 'active');
            })
            ->with(['allocations' => function ($query) use ($invoice) {
                $query->where('invoice_id', $invoice->id)
                    ->where('status', 'active');
            }])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(10);

        $directReceipt = $receipts->total() === 1
            ? $receipts->first()?->id
            : null;

        $uses = null;
        if ($receipts->total() === 0) {
            $uses = $invoice->creditUses()
                ->reorder()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(10);
        }

        return response()->json([
            'receipt_id' => $directReceipt,
            'title' => $invoice->number,
            'html' => view('invoices._invoice_notes_items', compact(
                'invoice', 'receipts', 'uses'
            ))->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request, InvoiceGenerator $generator)
    {
        $current = InvoicePeriod::today()->format('Y-m');
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $period = $data['period'] ?? $current;
        if ($period > $current || $period < '2000-01') {
            $period = $current;
        }

        [$year, $month] = array_map('intval', explode('-', $period));
        [$eligible, $skipped] = $generator->candidates($year, $month);

        return $this->page('create', compact('eligible', 'skipped', 'period', 'current'));
    }

    public function store(Request $request, InvoiceGenerator $generator)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'customer_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
            'confirmed' => ['accepted'],
        ], [
            'customer_ids.required' => 'Pilih minimal satu pelanggan.',
        ]);

        if ($data['period'] > InvoicePeriod::today()->format('Y-m') || $data['period'] < '2000-01') {
            return back()->withErrors(['period' => 'Bulan terbit tidak valid.']);
        }

        [$year, $month] = array_map('intval', explode('-', $data['period']));
        $issued = $skipped = $failed = 0;

        foreach ($data['customer_ids'] as $id) {
            try {
                [$invoice] = $generator->issue((int) $id, $year, $month, $request->user(), 'manual');
                $invoice ? $issued++ : $skipped++;
            } catch (UniqueConstraintViolationException $e) {
                $skipped++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return redirect()->route('invoices.index')->with('status',
            "{$issued} invoice diterbitkan, {$skipped} dilewati, {$failed} gagal. Saldo titip, jika ada, dipakai otomatis. Tidak ada perubahan MikroTik."
        );
    }

    public function show(Invoice $invoice)
    {
        return $this->page('show', [
            'invoice' => $invoice,
            'quote' => $invoice->snapshot,
        ]);
    }

    public function void(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($invoice, $request, $data) {
            Customer::withTrashed()->whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'unpaid' || (int) $locked->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Hanya invoice yang belum dibayar dan belum dibatalkan yang dapat dibatalkan.',
                ]);
            }

            $locked->update([
                'status' => 'void',
                'active_period_key' => null,
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'voided_by_name' => mb_substr($request->user()->name, 0, 255),
                'void_reason' => $data['reason'],
            ]);
        });

        return back()->with('status', 'Invoice dibatalkan. Data dan alasan pembatalan tetap tersimpan.');
    }

    private function page(string $name, array $data = [])
    {
        return response()->view('invoices.'.$name, $data)
            ->header('Cache-Control', 'private, no-store');
    }
}
