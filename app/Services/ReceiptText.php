<?php

namespace App\Services;

use App\Models\BillingReceipt;

class ReceiptText
{
    public const PLACEHOLDERS = [
        '{nomor_nota}',
        '{tanggal}',
        '{nama_pelanggan}',
        '{kode_pelanggan}',
        '{rincian_invoice}',
        '{jumlah_diterima}',
        '{sisa_tagihan}',
        '{titip_saldo}',
        '{saldo_titip}',
        '{metode}',
        '{referensi}',
        '{dicatat_oleh}',
    ];

    private const OPTIONAL = ['{sisa_tagihan}', '{titip_saldo}', '{saldo_titip}'];

    public const DEFAULT_TEMPLATE = "NOTA PEMBAYARAN\nNo: {nomor_nota}\nTanggal: {tanggal}\n\nPelanggan: {nama_pelanggan}\nKode: {kode_pelanggan}\n\nRincian pembayaran:\n{rincian_invoice}\n\nTotal diterima: {jumlah_diterima}\nSisa tagihan: {sisa_tagihan}\nTitip saldo baru: {titip_saldo}\nSaldo titip saat ini: {saldo_titip}\nMetode: {metode}\nReferensi: {referensi}\nDicatat oleh: {dicatat_oleh}\n\nTerima kasih.";

    public static function build(BillingReceipt $receipt, ?string $template): array
    {
        $receipt->loadMissing('customer');
        $snapshot = $receipt->receipt_snapshot;
        $legacy = ! is_array($snapshot)
            || ($snapshot['version'] ?? null) !== 1
            || ! is_array($snapshot['values'] ?? null);

        $values = $legacy
            ? ReceiptSnapshot::legacyValues($receipt)
            : $snapshot['values'];

        $template = filled($template) ? $template : self::DEFAULT_TEMPLATE;
        $kept = [];

        foreach (preg_split('/\R/', $template) as $line) {
            foreach (self::OPTIONAL as $marker) {
                if (
                    str_contains($line, $marker)
                    && ($values[$marker] ?? '') === ''
                ) {
                    continue 2;
                }
            }

            $kept[] = $line;
        }

        $text = trim((string) preg_replace(
            "/\n{3,}/",
            "\n\n",
            strtr(implode("\n", $kept), $values)
        ));

        if ($legacy) {
            $text .= "\n\nCatatan: nota lama tanpa snapshot. "
                .'Sisa tagihan dan total saldo setelah transaksi '
                .'tidak tersedia secara historis.';
        }

        if ($receipt->status === 'void') {
            $text = "*** DIBATALKAN ***\n".$text;
        }

        $phone = WhatsAppMessage::phone($receipt->customer?->whatsapp);

        return [
            'text' => $text,
            'phone' => $phone,
            'url' => $phone
                ? 'https://wa.me/'.$phone.'?text='.rawurlencode($text)
                : null,
        ];
    }
}
