<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Scopes\WilayahScope;
use App\Models\Wilayah;
use App\Services\InvoicePeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $month = $filters['month'] ?? InvoicePeriod::today()->format('Y-m');
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        $base = Expense::query()->whereBetween('spent_on', [$start, $end]);

        $activeTotal = (int) (clone $base)->where('status', 'active')->sum('amount');
        $activeCount = (clone $base)->where('status', 'active')->count();

        $expenses = (clone $base)
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return response()->view('expenses.index', [
            'expenses' => $expenses,
            'month' => $month,
            'activeTotal' => $activeTotal,
            'activeCount' => $activeCount,
            'requestKey' => (string) Str::uuid(),
            'wilayahList' => Wilayah::query()
                ->when(WilayahScope::allowedIds() !== null, fn ($q) => $q->whereIn('id', WilayahScope::allowedIds()))
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'spent_on' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:2000-01-01',
                'before_or_equal:'.InvoicePeriod::today()->format('Y-m-d'),
            ],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['required', 'in:cash,transfer'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'wilayah_id' => ['required', 'integer', 'exists:wilayah,id'],
        ]);

        $allowedWilayah = WilayahScope::allowedIds();
        if ($allowedWilayah !== null && ! in_array((int) $data['wilayah_id'], $allowedWilayah, true)) {
            throw ValidationException::withMessages([
                'wilayah_id' => 'Anda tidak memiliki akses ke wilayah ini.',
            ]);
        }

        $duplicate = fn () => ValidationException::withMessages([
            'expense' => 'Permintaan ini sudah diproses. Periksa daftar sebelum mencatat lagi.',
        ]);

        try {
            DB::transaction(function () use ($data, $request, $duplicate) {
                if (Expense::where('request_key', $data['request_key'])->exists()) {
                    throw $duplicate();
                }

                Expense::create([
                    'request_key' => $data['request_key'],
                    'spent_on' => $data['spent_on'],
                    'category' => $data['category'],
                    'amount' => (int) $data['amount'],
                    'method' => $data['method'],
                    'description' => trim($data['description']),
                    'status' => 'active',
                    'wilayah_id' => (int) $data['wilayah_id'],
                    'recorded_by' => $request->user()->id,
                    'recorded_by_name' => mb_substr($request->user()->name, 0, 255),
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            throw $duplicate();
        }

        return redirect()
            ->route('expenses.index', ['month' => substr($data['spent_on'], 0, 7)])
            ->with('status', 'Pengeluaran Rp'.number_format((int) $data['amount'], 0, ',', '.').' dicatat.');
    }

    public function void(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($expense, $request, $data) {
            $locked = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages([
                    'expense' => 'Pengeluaran ini sudah dibatalkan.',
                ]);
            }

            $locked->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'voided_by_name' => mb_substr($request->user()->name, 0, 255),
                'void_reason' => trim($data['reason']),
            ]);
        }, 3);

        return back()->with('status', 'Pengeluaran dibatalkan. Riwayatnya tetap tersimpan.');
    }
}
