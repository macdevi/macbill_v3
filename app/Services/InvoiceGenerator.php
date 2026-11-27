<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\CustomerNetworkAction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceGenerator
{
    public function candidates(int $year, int $month): array
    {
        $settings = BillingSetting::current();
        $eligible = [];
        $skipped = [];

        $customers = Customer::with(['package', 'router'])
            ->whereNotIn('service_status', ['stopped', 'pending'])
            ->orderBy('name')->get();

        foreach ($customers as $customer) {
            [$quote, $reason] = $this->prepare($customer, $settings, $year, $month);
            if ($quote) {
                $eligible[] = $quote;
            } else {
                $skipped[] = ['customer' => $customer, 'reason' => $reason];
            }
        }

        return [$eligible, $skipped];
    }

    public function prepare(
        Customer $customer,
        BillingSetting $settings,
        int $year,
        int $month,
        bool $auto = false
    ): array {
        if (
            Schema::hasTable('customer_network_actions')
            && CustomerNetworkAction::where(
                'open_customer_id', $customer->id
            )->exists()
        ) {
            return [null, 'Masih ada tindakan jaringan yang belum selesai atau perlu pemeriksaan.'];
        }

        $day = $customer->invoice_day ?? $settings->default_invoice_day;
        if ($day === null) {
            return [null, 'Tanggal tagihan belum diatur.'];
        }

        $period = InvoicePeriod::forIssueMonth($year, $month, (int) $day);

        if ($period['issued_on']->gt(InvoicePeriod::today())) {
            return [null, 'Tanggal terbit '.$period['issued_on']->format('d/m/Y').' belum tiba.'];
        }

        $key = $customer->id.':'.$period['period_start']->format('Y-m-d');
        $existing = Invoice::where('active_period_key', $key)->first(['number']);
        if ($existing) {
            return [null, 'Sudah ada invoice '.$existing->number.'.'];
        }

        if ($auto && $customer->first_billing_mode === null && ! Invoice::where('customer_id', $customer->id)
            ->where('status', '!=', 'void')->exists()) {
            return [null, 'Belum ada tagihan awal. Generate manual dahulu.'];
        }

        $proStartedOn = null;

        if ($customer->first_billing_mode !== null && $customer->started_at) {
            $startedOn = $customer->started_at->format('Y-m-d');

            if ($startedOn > $period['period_end']->format('Y-m-d')) {
                return [null, 'Layanan pelanggan baru mulai '.$customer->started_at->format('d/m/Y').'. Periode ini belum ditagih.'];
            }

            if ($startedOn > $period['period_start']->format('Y-m-d')) {
                if ($customer->first_billing_mode === 'skip') {
                    return [null, 'Periode awal dilewati sesuai pilihan saat pendaftaran.'];
                }

                if ($customer->first_billing_mode === 'prorata') {
                    $proStartedOn = $startedOn;
                }
            }
        }

        if ($customer->price_mode === 'custom') {
            $monthly = $customer->custom_monthly_price;
        } elseif ($customer->package) {
            $monthly = $customer->package->monthly_price;
        } else {
            return [null, 'Paket pelanggan tidak tersedia.'];
        }

        if ($monthly === null) {
            return [null, 'Harga pelanggan belum diisi.'];
        }

        $prorata = CustomerBillingProrata::forPeriod(
            $customer,
            (int) $monthly,
            $period['period_start']->format('Y-m-d'),
            $period['period_end']->format('Y-m-d'),
            $proStartedOn
        );

        try {
            $amounts = CustomerPricing::calculate(
                $prorata['price'],
                (int) $settings->tax_rate_bps,
                (bool) $customer->tax_enabled,
                (string) $settings->tax_mode
            );
        } catch (\InvalidArgumentException $e) {
            return [null, 'Pengaturan harga atau pajak tidak valid.'];
        }

        if ($amounts['total'] === 0) {
            return [null, 'Tagihan Rp0 (gratis), tidak dibuatkan invoice.'];
        }

        $grace = (int) ($settings->due_grace_days ?? 0);

        return [[
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_code' => $customer->code,
            'package_name' => $customer->package?->name ?? '-',
            'router_name' => $customer->router?->name ?? '-',
            'invoice_day' => (int) $day,
            'issued_on' => $period['issued_on']->format('Y-m-d'),
            'due_on' => $period['issued_on']->addDays($grace)->format('Y-m-d'),
            'period_start' => $period['period_start']->format('Y-m-d'),
            'period_end' => $period['period_end']->format('Y-m-d'),
            'period_key' => $key,
            'monthly_price' => (int) $monthly,
            'price_mode' => $customer->price_mode,
            'tax_name' => $settings->tax_name,
            'tax_rate_bps' => (int) $settings->tax_rate_bps,
            'tax_enabled' => (bool) $customer->tax_enabled,
            'tax_mode' => $settings->tax_mode,
            ...$amounts,
            'billing_prorata' => $prorata,
        ], null];
    }

    public function issue(
        int $customerId,
        int $year,
        int $month,
        ?User $user,
        string $source,
        bool $auto = false
    ): array {
        return DB::transaction(function () use ($customerId, $year, $month, $user, $source, $auto) {
            $customer = Customer::whereKey($customerId)->lockForUpdate()->first();
            if (! $customer) {
                return [null, 'Pelanggan tidak ditemukan.'];
            }

            if ($customer->service_status === 'pending') {
                return [null, 'Pelanggan masih menunggu aktivasi router. Invoice belum diterbitkan.'];
            }

            if ($customer->service_status === 'stopped') {
                return [null, 'Layanan pelanggan sudah berhenti. Invoice tidak diterbitkan.'];
            }

            $customer->load(['package', 'router']);

            [$quote, $reason] = $this->prepare(
                $customer, BillingSetting::current(), $year, $month, $auto
            );
            if (! $quote) {
                return [null, $reason];
            }

            $invoice = Invoice::create([
                'active_period_key' => $quote['period_key'],
                'customer_id' => $customer->id,
                'created_by' => $user?->id,
                'source' => $source,
                'customer_name' => $quote['customer_name'],
                'customer_code' => $quote['customer_code'],
                'issued_on' => $quote['issued_on'],
                'due_on' => $quote['due_on'],
                'period_start' => $quote['period_start'],
                'period_end' => $quote['period_end'],
                'price' => $quote['price'],
                'net' => $quote['net'],
                'tax' => $quote['tax'],
                'total' => $quote['total'],
                'status' => 'unpaid',
                'snapshot' => [
                    ...$quote,
                    'created_by_name' => $user?->name ?? 'Penjadwal',
                    'source' => $source,
                ],
            ]);

            $invoice->number = 'INV-'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT);
            $invoice->save();

            (new BillingLedger)->applyBalance($customer->id, $user);
            $invoice->refresh();

            return [$invoice, null];
        });
    }
}
