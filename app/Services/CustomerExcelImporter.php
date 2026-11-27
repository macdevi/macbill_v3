<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\InternetPackage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use ZipArchive;

class CustomerExcelImporter
{
    public function read(string $path): array
    {
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Invalid size');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Invalid XLSX');
        }

        try {
            $expanded = 0;
            if ($zip->numFiles > 2000) {
                throw new \RuntimeException('Too many entries');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new \RuntimeException('Invalid archive');
                }
                $expanded += (int) $stat['size'];
                $name = strtolower($stat['name']);
                if (
                    $expanded > 32 * 1024 * 1024 ||
                    str_contains($name, 'vbaproject') ||
                    str_starts_with($name, 'xl/externallinks/')
                ) {
                    throw new \RuntimeException('Unsupported archive');
                }
            }
        } finally {
            $zip->close();
        }

        $book = null;
        try {
            $reader = new Xlsx;
            if (! $reader->canRead($path)) {
                throw new \RuntimeException('Unsupported file');
            }

            $info = collect($reader->listWorksheetInfo($path))
                ->firstWhere('worksheetName', 'Pelanggan');

            if (! $info || $info['totalRows'] > 1001 || $info['totalColumns'] > 13) {
                throw new \RuntimeException('Unsupported dimensions');
            }

            $reader->setLoadSheetsOnly(['Pelanggan']);
            $reader->setReadEmptyCells(false);
            $book = $reader->load($path);
            $sheet = $book->getSheetByName('Pelanggan');

            foreach (CustomerExcelWorkbook::HEADERS as $i => $header) {
                $cell = $sheet->getCell([$i + 1, 1]);
                if (
                    $cell->getDataType() === DataType::TYPE_FORMULA ||
                    trim((string) $cell->getValue()) !== $header
                ) {
                    throw new \RuntimeException('Header mismatch');
                }
            }

            $rows = [];
            for ($r = 2; $r <= min(1001, $sheet->getHighestDataRow()); $r++) {
                $values = [];
                $errors = [];

                for ($c = 1; $c <= 13; $c++) {
                    $cell = $sheet->getCell([$c, $r]);
                    $value = $cell->getValue();

                    // Total bulanan tidak digunakan sebagai input.
                    if ($c === 10) {
                        $values[] = '';

                        continue;
                    }

                    if (
                        in_array($cell->getDataType(), [
                            DataType::TYPE_FORMULA, DataType::TYPE_ERROR,
                        ], true)
                    ) {
                        $errors[] = 'Kolom input berisi formula atau error Excel.';
                        $value = '';
                    } elseif ($value instanceof RichText) {
                        $value = $value->getPlainText();
                    } elseif (! is_scalar($value) && $value !== null) {
                        $errors[] = 'Ada tipe isi sel yang tidak didukung.';
                        $value = '';
                    }

                    if (
                        in_array($c, [2, 6, 7], true) &&
                        $value !== null && $value !== '' &&
                        $cell->getDataType() === DataType::TYPE_NUMERIC
                    ) {
                        $errors[] = 'WhatsApp, username, dan password harus berformat Text.';
                    }

                    if (
                        $c === 13 && is_numeric($value) &&
                        str_contains($cell->getStyle()->getNumberFormat()->getFormatCode(), '%')
                    ) {
                        $value = number_format((float) $value * 100, 2, '.', '').'%';
                    }

                    if (mb_strlen((string) ($value ?? '')) > 10000) {
                        $errors[] = 'Ada isi sel yang terlalu panjang.';
                        $value = '';
                    }

                    $values[] = (string) ($value ?? '');
                }

                if (
                    count(array_filter($values, fn ($v) => $v !== '')) === 0 &&
                    $errors === []
                ) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $r,
                    'values' => $values,
                    'read_errors' => array_values(array_unique($errors)),
                ];
            }

            return $rows;
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    public function review(array $rows, string $startedAt): array
    {
        $settings = BillingSetting::current();
        $routers = ImportWilayah::routers()->get([
            'id', 'name', 'host', 'port', 'use_tls', 'username',
        ]);
        $packages = InternetPackage::where('is_active', true)
            ->with(['routers' => fn ($q) => $q->select('network_routers.id')])
            ->get();

        $existing = [];
        foreach (
            Customer::withoutGlobalScopes()->get(['network_router_id', 'pppoe_username', 'static_ip']) as $c
        ) {
            if ($c->pppoe_username !== null) {
                $existing[$this->key($c->network_router_id, 'pppoe', $c->pppoe_username)] = true;
            }
            if ($c->static_ip !== null) {
                $existing[$this->key($c->network_router_id, 'static', $c->static_ip)] = true;
            }
        }

        $result = [];
        $fileCounts = [];

        foreach ($rows as $source) {
            $v = $source['values'];
            $errors = $source['read_errors'];
            $routerName = trim($v[3]);
            $packageName = trim($v[4]);

            $matches = $routers->filter(fn ($r) => $r->name === $routerName);
            $router = $matches->count() === 1 ? $matches->first() : null;

            if (! $router) {
                $errors[] = 'Router tidak ditemukan atau namanya tidak unik.';
            }

            $matches = $packages->filter(fn ($p) => $p->name === $packageName &&
                in_array($p->service_type, ['pppoe', 'static'], true) &&
                (
                    $p->service_type === 'static' ||
                    ($router && $p->routers->contains('id', $router->id))
                )
            );
            $package = $matches->count() === 1 ? $matches->first() : null;

            if (! $package) {
                $errors[] = 'Paket tidak ditemukan, nonaktif, ambigu, atau tidak sesuai router.';
            }

            $type = $package?->service_type ?? '';
            $status = match (mb_strtolower(trim($v[10]))) {
                'aktif', 'active' => 'active',
                'berhenti', 'stopped' => 'stopped',
                default => '',
            };

            $invoiceDay = trim($v[11]);
            if ($invoiceDay === '' || mb_strtolower($invoiceDay) === 'ikuti bawaan') {
                $invoiceDay = null;
            }

            if ($invoiceDay === null && $settings->default_invoice_day === null) {
                $errors[] = 'Tanggal tagihan bawaan belum diatur.';
            }

            $taxText = trim($v[12]);
            $taxEnabled = (bool) $settings->default_tax_enabled;

            if ($taxText !== '') {
                $taxText = trim(rtrim($taxText, '%'));
                if (! preg_match('/^[0-9]{1,3}(?:\.[0-9]{1,2})?$/D', $taxText)) {
                    $errors[] = 'Persentase pajak tidak valid.';
                } else {
                    $parts = explode('.', $taxText);
                    $bps = (int) $parts[0] * 100
                        + (int) str_pad($parts[1] ?? '', 2, '0');

                    if ($bps === 0) {
                        $taxEnabled = false;
                    } elseif ($bps === (int) $settings->tax_rate_bps) {
                        $taxEnabled = true;
                    } else {
                        $errors[] = 'Pajak harus 0% atau sama dengan tarif di Pengaturan.';
                    }
                }
            }

            if ($type === 'pppoe' && trim($v[5]) !== $v[5]) {
                $errors[] = 'Username memiliki spasi di awal/akhir. Perbaiki di file.';
            }
            if ($type === 'pppoe' && trim($v[7]) !== '') {
                $errors[] = 'Untuk PPPoE, kosongkan IP pelanggan.';
            }
            if ($type === 'static' && ($v[5] !== '' || $v[6] !== '')) {
                $errors[] = 'Untuk IP statis, kosongkan username dan password PPPoE.';
            }

            $custom = trim($v[8]);
            $data = [
                'name' => trim($v[0]),
                'whatsapp' => $v[1] === '' ? null : $v[1],
                'address' => $v[2] === '' ? null : $v[2],
                'network_router_id' => $router?->id,
                'internet_package_id' => $package?->id,
                'service_type' => $type,
                'pppoe_username' => $type === 'pppoe' ? $v[5] : null,
                'pppoe_password' => $type === 'pppoe' && $v[6] !== '' ? $v[6] : null,
                'static_ip' => $type === 'static' ? trim($v[7]) : null,
                'started_at' => $startedAt,
                'invoice_day' => $invoiceDay,
                'due_day' => $invoiceDay,
                'service_status' => $status,
                'price_mode' => $custom === '' ? 'package' : 'custom',
                'custom_monthly_price' => $custom === '' ? null : $custom,
                'tax_enabled' => $taxEnabled,
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:150'],
                'whatsapp' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string', 'max:5000'],
                'network_router_id' => ['required', 'integer'],
                'internet_package_id' => ['required', 'integer'],
                'service_type' => ['required', 'in:pppoe,static'],
                'pppoe_username' => ['nullable', 'required_if:service_type,pppoe', 'string', 'max:100'],
                'pppoe_password' => ['nullable', 'string', 'max:1024'],
                'static_ip' => ['nullable', 'required_if:service_type,static', 'ip'],
                'started_at' => ['required', 'date_format:Y-m-d'],
                'invoice_day' => ['nullable', 'integer', 'between:1,31'],
                'service_status' => ['required', 'in:active,stopped'],
                'custom_monthly_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            ]);

            $labels = [
                'name' => 'Nama pelanggan', 'whatsapp' => 'WhatsApp',
                'address' => 'Alamat', 'network_router_id' => 'Router',
                'internet_package_id' => 'Paket', 'service_type' => 'Jenis layanan',
                'pppoe_username' => 'Username PPPoE', 'pppoe_password' => 'Password PPPoE',
                'static_ip' => 'IP pelanggan', 'started_at' => 'Tanggal mulai',
                'invoice_day' => 'Tanggal tagihan', 'service_status' => 'Status layanan',
                'custom_monthly_price' => 'Harga khusus',
            ];
            if ($validator->fails()) {
                foreach ($validator->errors()->keys() as $field) {
                    $errors[] = ($labels[$field] ?? 'Isian').' belum valid.';
                }
            }

            $identity = $type === 'pppoe' ? $data['pppoe_username'] : $data['static_ip'];
            $key = $router && $type !== '' && filled($identity)
                ? $this->key($router->id, $type, $identity) : null;

            if ($key !== null) {
                $fileCounts[$key] = ($fileCounts[$key] ?? 0) + 1;
            }

            $quote = null;
            if ($errors === []) {
                $data['invoice_day'] = $invoiceDay === null ? null : (int) $invoiceDay;
                $data['due_day'] = $data['invoice_day'];
                $data['custom_monthly_price'] = $custom === '' ? null : (int) $custom;

                $quote = CustomerPricing::calculate(
                    $custom === '' ? (int) $package->monthly_price : (int) $custom,
                    (int) $settings->tax_rate_bps,
                    $taxEnabled,
                    $settings->tax_mode
                );
            }

            $fingerprint = $router ? [
                $router->host, $router->port, $router->use_tls, $router->username,
            ] : null;

            $result[] = [
                'row_number' => $source['row_number'],
                'data' => $data,
                'router_name' => $routerName,
                'package_name' => $packageName,
                'errors' => array_values(array_unique($errors)),
                'duplicate' => $key !== null && isset($existing[$key]),
                'key' => $key,
                'quote' => $quote,
                'tax_percent' => CustomerExcelWorkbook::percent(
                    $taxEnabled ? (int) $settings->tax_rate_bps : 0
                ),
                'effective_day' => $invoiceDay ?? $settings->default_invoice_day,
                'signature' => hash('sha256', serialize([
                    $data, $quote, $fingerprint,
                    $invoiceDay ?? $settings->default_invoice_day,
                    $taxEnabled ? [
                        $settings->tax_name, $settings->tax_mode, $settings->tax_rate_bps,
                    ] : null,
                ])),
            ];
        }

        foreach ($result as &$row) {
            if ($row['key'] !== null && ($fileCounts[$row['key']] ?? 0) > 1) {
                $row['errors'][] = 'Identitas berulang dalam file. Sisakan satu baris.';
            }
            $row['ready'] = ! $row['duplicate'] && $row['errors'] === [];
        }
        unset($row);

        return $result;
    }

    private function key(int $routerId, string $type, string $identity): string
    {
        return hash('sha256', serialize([$routerId, $type, $identity]));
    }
}
