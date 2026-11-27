<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class WhatsAppMessage
{
    public const PLACEHOLDERS = [
        '{nama_pelanggan}',
        '{kode_pelanggan}',
        '{nomor_invoice}',
        '{nama_paket}',
        '{periode}',
        '{tanggal_terbit}',
        '{jatuh_tempo}',
        '{total_tagihan}',
        '{dibayar}',
        '{sisa_tagihan}',
        '{status_tagihan}',
    ];

    public const DEFAULT_TEMPLATE = "Halo {nama_pelanggan},\n\nBerikut informasi tagihan internet Anda:\n\nNomor invoice: {nomor_invoice}\nPaket: {nama_paket}\nPeriode: {periode}\nTanggal terbit: {tanggal_terbit}\nJatuh tempo: {jatuh_tempo}\nTotal: {total_tagihan}\nDibayar: {dibayar}\nSisa tagihan: {sisa_tagihan}\nStatus: {status_tagihan}\n\nTerima kasih.";

    public static function phone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : null;
    }

    public static function build(Invoice $invoice, ?string $template): array
    {
        $quote = $invoice->snapshot ?? [];
        $template = filled($template) ? $template : self::DEFAULT_TEMPLATE;
        $fmt = fn ($date) => Carbon::parse($date)->format('d/m/Y');

        $message = strtr($template, [
            '{nama_pelanggan}' => $invoice->customer_name,
            '{kode_pelanggan}' => $invoice->customer_code,
            '{nomor_invoice}' => (string) $invoice->number,
            '{nama_paket}' => (string) ($quote['package_name'] ?? '-'),
            '{periode}' => $fmt($invoice->period_start).' sampai '.$fmt($invoice->period_end),
            '{tanggal_terbit}' => $fmt($invoice->issued_on),
            '{jatuh_tempo}' => $fmt($invoice->due_on),
            '{total_tagihan}' => 'Rp'.number_format($invoice->total, 0, ',', '.'),
            '{dibayar}' => 'Rp'.number_format((int) $invoice->paid_amount, 0, ',', '.'),
            '{sisa_tagihan}' => 'Rp'.number_format(max(0, (int) $invoice->total - (int) $invoice->paid_amount), 0, ',', '.'),
            '{status_tagihan}' => $invoice->status_label,
        ]);

        $phone = self::phone($invoice->customer?->whatsapp);

        return [
            'message' => $message,
            'phone' => $phone,
            'url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ];
    }
}
