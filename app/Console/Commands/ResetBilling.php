<?php

namespace App\Console\Commands;

use App\Services\BillingReset;
use Illuminate\Console\Command;

class ResetBilling extends Command
{
    protected $signature = 'billing:reset {--yes : Lewati ketik konfirmasi}';

    protected $description = 'Kosongkan semua data billing (router, paket, pelanggan, tagihan, pengeluaran) agar mulai dari nol';

    public function handle(): int
    {
        if (! config('billing_reset.enabled')) {
            $this->error('Ditolak: set BILLING_RESET_ENABLED=true di .env untuk mengizinkan perintah ini.');

            return self::FAILURE;
        }

        $rows = [];
        foreach (BillingReset::counts() as $table => $count) {
            $rows[] = [$table, $count];
        }
        $this->table(['Tabel', 'Baris'], $rows);

        if (! $this->option('yes') && $this->ask('Ketik KOSONGKAN untuk menghapus semua data di atas') !== 'KOSONGKAN') {
            $this->warn('Dibatalkan, tidak ada data yang diubah.');

            return self::FAILURE;
        }

        BillingReset::run();
        $this->info('Data billing dikosongkan. Pengguna, wilayah, dan pengaturan billing tidak diubah. MikroTik tidak disentuh.');

        return self::SUCCESS;
    }
}
