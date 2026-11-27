<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Services\ReceiptText;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingSettingController extends Controller
{
    public function index()
    {
        return response()->view('billing-settings.index', [
            'settings' => BillingSetting::current(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_invoice_day' => ['required', 'integer', 'between:1,31'],
            'isolation_mode' => ['required', 'in:manual'],
            'tax_name' => ['required', 'string', 'max:50'],
            'tax_rate' => ['required', 'regex:/^[0-9]{1,3}(?:\.[0-9]{1,2})?$/D', 'numeric', 'between:0,100'],
            'tax_mode' => ['required', 'in:include,exclude'],
            'default_tax_enabled' => ['required', 'boolean'],
            'due_grace_days' => ['required', 'integer', 'between:0,60'],
            'wa_template' => ['nullable', 'string', 'max:1500'],
            'receipt_template' => ['nullable', 'string', 'max:2000'],
        ], [
            'default_due_day.required' => 'Pilih tanggal jatuh tempo bawaan.',
            'default_due_day.integer' => 'Tanggal jatuh tempo harus berupa angka.',
            'default_due_day.between' => 'Tanggal jatuh tempo harus antara 1 sampai 31.',
            'isolation_mode.in' => 'Isolir otomatis belum tersedia. Gunakan mode Manual.',
        ]);

        $rateParts = explode('.', (string) $data['tax_rate']);
        $rateBps = ((int) $rateParts[0] * 100)
            + (int) str_pad($rateParts[1] ?? '', 2, '0');

        $allowedMarkers = array_map(
            fn ($marker) => trim($marker, '{}'),
            WhatsAppMessage::PLACEHOLDERS
        );
        preg_match_all('/\{([a-z_]+)\}/', (string) ($data['wa_template'] ?? ''), $found);
        $unknown = array_values(array_diff($found[1], $allowedMarkers));
        if ($unknown) {
            throw ValidationException::withMessages([
                'wa_template' => 'Penanda tidak dikenal: {'.implode('}, {', $unknown).'}',
            ]);
        }

        $receiptMarkers = array_map(
            fn ($marker) => trim($marker, '{}'),
            ReceiptText::PLACEHOLDERS
        );
        preg_match_all('/\{([a-z_]+)\}/', (string) ($data['receipt_template'] ?? ''), $receiptFound);
        $receiptUnknown = array_values(array_diff($receiptFound[1], $receiptMarkers));
        if ($receiptUnknown) {
            throw ValidationException::withMessages([
                'receipt_template' => 'Penanda nota tidak dikenal: {'.implode('}, {', $receiptUnknown).'}',
            ]);
        }

        $settings = BillingSetting::current();
        $settings->fill([
            'default_due_day' => $data['default_invoice_day'],
            'default_invoice_day' => $data['default_invoice_day'],
            'missing_day_policy' => 'last_day',
            'due_change_scope' => 'future_invoices',
            'isolation_mode' => 'manual',
            'tax_name' => $data['tax_name'],
            'tax_rate_bps' => $rateBps,
            'tax_mode' => $data['tax_mode'],
            'default_tax_enabled' => (bool) $data['default_tax_enabled'],
            'due_grace_days' => (int) $data['due_grace_days'],
            'wa_template' => filled($data['wa_template'] ?? null) ? trim($data['wa_template']) : null,
            'receipt_template' => filled($data['receipt_template'] ?? null) ? trim($data['receipt_template']) : null,
        ])->save();

        return redirect()->route('billing-settings.index')
            ->with('status', 'Pengaturan disimpan. Tidak ada tagihan lama atau konfigurasi MikroTik yang diubah.');
    }
}
