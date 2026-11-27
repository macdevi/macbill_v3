<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerExcelWorkbook
{
    public const HEADERS = [
        'Nama pelanggan',
        'WhatsApp',
        'Alamat',
        'Router',
        'Paket',
        'Username PPPoE',
        'Password PPPoE',
        'IP pelanggan',
        'Harga khusus',
        'Total bulanan',
        'Status layanan',
        'Tanggal tagihan',
        'Pajak (%)',
    ];

    public function template(): Spreadsheet
    {
        return $this->base(1001);
    }

    public function export(iterable $customers, BillingSetting $settings): Spreadsheet
    {
        $book = $this->base(1);
        $sheet = $book->getSheetByName('Pelanggan');
        $row = 2;

        foreach ($customers as $customer) {
            $pricing = CustomerPricing::forCustomer($customer, $settings);
            $invoiceDay = $customer->invoice_day ?? $settings->default_invoice_day;

            $values = [
                $customer->name,
                $customer->whatsapp,
                $customer->address,
                $customer->router->name,
                $customer->package->name,
                $customer->pppoe_username,
                $customer->pppoe_password,
                $customer->static_ip,
                $customer->price_mode === 'custom'
                    ? $customer->custom_monthly_price : null,
                $pricing['total'],
                $customer->service_status === 'active' ? 'Aktif' : 'Berhenti',
                $invoiceDay === null ? '' : (string) $invoiceDay,
                self::percent($customer->tax_enabled ? $settings->tax_rate_bps : 0),
            ];

            foreach ($values as $offset => $value) {
                $numeric = in_array($offset, [8, 9], true) && $value !== null;

                $sheet->setCellValueExplicit(
                    [$offset + 1, $row],
                    $value ?? '',
                    $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING
                );
            }
            $row++;
        }

        $last = max(2, $row - 1);
        $sheet->getStyle('I2:J'.$last)
            ->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setAutoFilter('A1:M'.max(1, $row - 1));

        return $book;
    }

    public static function percent(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $decimal = $basisPoints % 100;

        return (string) $whole.(
            $decimal === 0
                ? ''
                : '.'.rtrim(str_pad((string) $decimal, 2, '0', STR_PAD_LEFT), '0')
        ).'%';
    }

    private function base(int $validationLastRow): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Tagihan dan Management Jaringan');
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Pelanggan');

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValueExplicit(
                [$index + 1, 1], $header, DataType::TYPE_STRING
            );
            $sheet->getColumnDimension(
                Coordinate::stringFromColumnIndex($index + 1)
            )->setWidth(24);
        }

        foreach (['A', 'C', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(32);
        }

        $sheet->freezePane('B2');
        $sheet->setAutoFilter('A1:M1');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)
            ->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:M1')->getFill()
            ->setFillType('solid')->getStartColor()->setARGB('FF15334A');
        $sheet->getStyle('A1:M1')->getAlignment()->setWrapText(true);

        $settings = BillingSetting::current();
        $reference = $book->createSheet();
        $reference->setTitle('Referensi');

        $lists = [
            'ExcelRouterList' => NetworkRouter::orderBy('name')->pluck('name')
                ->unique()->values()->all(),
            'ExcelPackageList' => InternetPackage::where('is_active', true)
                ->orderBy('name')->pluck('name')->unique()->values()->all(),
            'ExcelStatusList' => ['Aktif', 'Berhenti'],
            'ExcelInvoiceList' => array_merge(
                ['Ikuti bawaan'], array_map('strval', range(1, 31))
            ),
            'ExcelTaxList' => array_values(array_unique([
                '0%', self::percent((int) $settings->tax_rate_bps),
            ])),
        ];

        $column = 1;
        foreach ($lists as $name => $items) {
            $reference->setCellValueExplicit(
                [$column, 1], $name, DataType::TYPE_STRING
            );
            if ($items === []) {
                $items = [''];
            }

            foreach ($items as $offset => $item) {
                $reference->setCellValueExplicit(
                    [$column, $offset + 2], $item, DataType::TYPE_STRING
                );
            }

            $letter = Coordinate::stringFromColumnIndex($column);
            $book->addNamedRange(new NamedRange(
                $name,
                $reference,
                '$'.$letter.'$2:$'.$letter.'$'.(count($items) + 1)
            ));
            $column++;
        }

        if ($validationLastRow >= 2) {
            $sheet->getStyle('A2:M'.$validationLastRow)
                ->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('I2:J'.$validationLastRow)
                ->getNumberFormat()->setFormatCode('0');

            foreach ([
                'D' => 'ExcelRouterList',
                'E' => 'ExcelPackageList',
                'K' => 'ExcelStatusList',
                'L' => 'ExcelInvoiceList',
                'M' => 'ExcelTaxList',
            ] as $col => $range) {
                $validation = $sheet->getDataValidation(
                    $col.'2:'.$col.$validationLastRow
                );
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Pilihan tidak tersedia');
                $validation->setError('Pilih nilai dari dropdown.');
                $validation->setFormula1('='.$range);
            }

            $sheet->getStyle('J2:J'.$validationLastRow)->getFill()
                ->setFillType('solid')->getStartColor()->setARGB('FFE8EDF3');
        }

        $reference->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $guide = $book->createSheet();
        $guide->setTitle('Panduan');
        $lines = [
            'FORMAT PELANGGAN RINGKAS',
            'Gunakan lembar Pelanggan. Dropdown tersedia untuk 1.000 baris.',
            'Nama pelanggan, Router, Paket, dan Status layanan diisi untuk setiap pelanggan.',
            'Jenis layanan mengikuti paket. Paket PPPoE harus sesuai pemetaan router.',
            'PPPoE: username wajib, password opsional. IP pelanggan dikosongkan.',
            'IP statis: IP pelanggan wajib. Username/password PPPoE dikosongkan.',
            'WhatsApp, username, dan password harus berupa teks agar angka nol dan karakter tetap utuh.',
            'Harga khusus: kosong berarti mengikuti harga paket. Isi angka rupiah tanpa Rp atau pemisah ribuan untuk harga khusus.',
            'Total bulanan: tidak perlu diisi saat import. Aplikasi menghitung ulang nominalnya.',
            'Tanggal tagihan: angka 1-31 atau Ikuti bawaan. Kosong berarti ikuti bawaan. Digunakan untuk penerbitan invoice sekaligus batas pembayaran.',
            'Tanggal yang tidak tersedia pada bulan terkait menggunakan hari terakhir bulan tersebut.',
            'Tanggal mulai layanan akan dipilih pada halaman unggah, tidak diulang dalam setiap baris.',
            'Pajak: 0% berarti tidak dikenakan. Pilih tarif Pengaturan untuk dikenakan pajak. Kosong mengikuti default pelanggan baru.',
            'Jika tarif Pengaturan 0%, kolom ini tidak membedakan status dikenakan/tidak dikenakan. Tinjau pilihannya pada preview.',
            'Metode Include/Exclude mengikuti Pengaturan. Tidak ada tarif pajak khusus per pelanggan pada file ini.',
            'Export menampilkan tanggal tagihan efektif. Jika diimport sebagai data baru, angka tersebut menjadi tanggal khusus.',
            'Daftar router/paket adalah salinan saat file dibuat. Unduh template baru bila data billing berubah.',
            'Import hanya menambah pelanggan baru; data yang sudah ada tidak diperbarui diam-diam.',
            'File export berisi password terbaca. Simpan dengan aman dan jangan masukkan ke repositori.',
        ];

        foreach ($lines as $offset => $line) {
            $guide->setCellValueExplicit(
                [1, $offset + 1], $line, DataType::TYPE_STRING
            );
            $guide->getRowDimension($offset + 1)->setRowHeight(36);
        }
        $guide->getColumnDimension('A')->setWidth(115);
        $guide->getStyle('A1:A'.count($lines))->getAlignment()->setWrapText(true);
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $guide->freezePane('A2');

        $book->setActiveSheetIndex(0);

        return $book;
    }
}
