<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Services\LoginScreen;
use App\Services\ReceiptText;
use App\Services\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function updateLoginScreen(Request $request): RedirectResponse
    {
        $settings = BillingSetting::current();

        if ($request->has('reset')) {
            $settings->forceFill(['login_screen' => null])->save();

            return redirect()->route('billing-settings.index')
                ->with('status', 'Tampilan login dikembalikan ke bawaan.');
        }

        $line = ['required', 'string', 'regex:/^[^\r\n]*$/'];

        $data = $request->validate([
            'login_command' => [...$line, 'max:80'],
            'login_title' => [...$line, 'max:120'],
            'login_tagline' => [...$line, 'max:80'],
            'login_modules' => ['required', 'string', 'max:400'],
            'login_spinner' => [...$line, 'max:60'],
            'login_message' => [...$line, 'max:100'],
            'login_splash' => ['required', 'boolean'],
            'login_rain' => ['required', 'boolean'],
            'login_accent' => ['required', Rule::in(array_keys(LoginScreen::ACCENTS))],
        ], [
            'login_command.regex' => 'Perintah pembuka harus satu baris.',
            'login_title.regex' => 'Baris judul harus satu baris.',
            'login_tagline.regex' => 'Tagline harus satu baris.',
            'login_spinner.regex' => 'Teks spinner harus satu baris.',
            'login_message.regex' => 'Pesan sebelum login harus satu baris.',
            'login_accent.in' => 'Warna terminal tidak valid.',
        ]);

        $modules = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $data['login_modules']) ?: []),
            fn ($module) => $module !== ''
        ));

        if ($modules === [] || count($modules) > 6) {
            throw ValidationException::withMessages([
                'login_modules' => 'Isi 1 sampai 6 baris modul, satu modul per baris.',
            ]);
        }

        foreach ($modules as $module) {
            if (mb_strlen($module) > 30) {
                throw ValidationException::withMessages([
                    'login_modules' => 'Setiap nama modul maksimal 30 karakter.',
                ]);
            }
        }

        $allowed = array_map(fn ($marker) => trim($marker, '{}'), LoginScreen::PLACEHOLDERS);

        foreach (['login_command', 'login_title', 'login_tagline', 'login_spinner', 'login_message', 'login_modules'] as $field) {
            preg_match_all('/\{([a-z_]+)\}/', (string) $data[$field], $found);
            $unknown = array_values(array_diff($found[1], $allowed));

            if ($unknown) {
                throw ValidationException::withMessages([
                    $field => 'Penanda tidak dikenal: {'.implode('}, {', $unknown).'}',
                ]);
            }
        }

        $settings->forceFill(['login_screen' => [
            'command' => trim($data['login_command']),
            'title' => trim($data['login_title']),
            'tagline' => trim($data['login_tagline']),
            'modules' => $modules,
            'spinner' => trim($data['login_spinner']),
            'message' => trim($data['login_message']),
            'splash' => (bool) $data['login_splash'],
            'rain' => (bool) $data['login_rain'],
            'accent' => $data['login_accent'],
        ]])->save();

        return redirect()->route('billing-settings.index')
            ->with('status', 'Tampilan login disimpan.');
    }
}
