<?php

namespace App\Console\Commands;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Services\InvoiceGenerator;
use App\Services\InvoicePeriod;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices {--commit : Terbitkan invoice. Tanpa opsi ini hanya simulasi}';

    protected $description = 'Terbitkan invoice pelanggan yang tanggal tagihannya hari ini (WIB)';

    public function handle(InvoiceGenerator $generator): int
    {
        $today = InvoicePeriod::today();
        $commit = (bool) $this->option('commit');
        $settings = BillingSetting::current();
        $this->info(($commit ? 'TERBIT' : 'SIMULASI').' untuk '.$today->format('Y-m-d').' (WIB)');

        $issued = $skipped = 0;
        foreach (Customer::whereNotIn('service_status', ['stopped', 'pending'])->orderBy('id')->get() as $customer) {
            $day = $customer->invoice_day ?? $settings->default_invoice_day;
            if ($day === null) {
                continue;
            }

            $period = InvoicePeriod::forIssueMonth($today->year, $today->month, (int) $day);
            if (!$period['issued_on']->isSameDay($today)) {
                continue;
            }

            if (!$commit) {
                [$quote, $reason] = $generator->prepare($customer, $settings, $today->year, $today->month, true);
                $this->line($customer->code.' | '.($quote ? 'AKAN TERBIT Rp'.number_format($quote['total'], 0, ',', '.') : 'DILEWATI: '.$reason));
                continue;
            }

            [$invoice, $reason] = $generator->issue($customer->id, $today->year, $today->month, null, 'auto', true);
            if ($invoice) {
                $issued++;
                $this->line($customer->code.' | '.$invoice->number);
            } else {
                $skipped++;
                $this->line($customer->code.' | dilewati: '.$reason);
            }
        }

        if ($commit) {
            $this->info("Selesai: {$issued} terbit, {$skipped} dilewati.");
        }

        return self::SUCCESS;
    }
}
