<?php

namespace App\Console\Commands;

use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\NetworkRouter;
use App\Models\Scopes\WilayahScope;
use App\Models\User;
use App\Services\BillingLedger;
use App\Services\CustomerProvisioning;
use App\Services\CustomerRemoval;
use App\Services\InvoiceGenerator;
use App\Services\InvoicePeriod;
use App\Services\RouterSecretReader;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditFull extends Command
{
    protected $signature = 'audit:full
        {--skenario : Skenario tulis di dalam transaksi yang selalu dibatalkan}
        {--router : Baca-saja ke MikroTik untuk rekonsiliasi akun}
        {--output=storage/app/audit/report.txt : Lokasi laporan}';

    protected $description = 'Audit menyeluruh (baca-saja secara bawaan) dan tulis report.txt';

    private array $body = [];

    private array $tally = ['PASS' => 0, 'INFO' => 0, 'WARN' => 0, 'FAIL' => 0, 'SKIP' => 0];

    private int $secNo = 0;

    public function handle(): int
    {
        $started = microtime(true);
        $steps = [
            'Lingkungan' => 'sectionEnvironment',
            'Sintaks PHP dan Blade' => 'sectionLint',
            'Skema dan relasi database' => 'sectionSchema',
            'Rute dan otorisasi (statis)' => 'sectionRoutes',
            'Integritas pelanggan dan jaringan' => 'sectionCustomers',
            'Akuntansi' => 'sectionAccounting',
            'Isolasi wilayah per pengguna' => 'sectionWilayah',
            'Uji halaman (smoke test HTTP)' => 'sectionSmoke',
            'Keamanan data dan konfigurasi' => 'sectionSecurity',
            'Pemindaian kode' => 'sectionCodeScan',
        ];
        if ($this->option('router')) {
            $steps['Rekonsiliasi MikroTik (baca-saja)'] = 'sectionRouter';
        }
        if ($this->option('skenario')) {
            $steps['Skenario transaksional (dibatalkan)'] = 'sectionScenario';
        }

        foreach ($steps as $title => $method) {
            $this->sec($title);
            $t = microtime(true);
            try {
                $this->{$method}();
            } catch (\Throwable $e) {
                $this->mark('FAIL', 'Bagian berhenti karena error: '.$this->safe($e));
            }
            $this->output->writeln(sprintf('  %-45s %.1fs', $title, microtime(true) - $t));
        }
        Auth::guard()->forgetUser();

        $head = [
            str_repeat('#', 78),
            'LAPORAN AUDIT tagihan-jaringan',
            'Dibuat   : '.now()->toDateTimeString().' '.config('app.timezone'),
            'Durasi   : '.round(microtime(true) - $started, 1).' detik',
            'Opsi     : skenario='.($this->option('skenario') ? 'ya' : 'tidak').' router='.($this->option('router') ? 'ya' : 'tidak'),
            'Ringkasan: '.collect($this->tally)->map(fn ($v, $k) => "$k=$v")->implode('  '),
            'Catatan  : tanpa kata sandi/kredensial; data pelanggan hanya berupa id.',
            str_repeat('#', 78),
        ];

        $out = $this->option('output');
        $path = str_starts_with($out, '/') ? $out : base_path($out);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, implode("\n", array_merge($head, $this->body))."\n");

        $this->newLine();
        $this->output->writeln('Ringkasan: '.collect($this->tally)->map(fn ($v, $k) => "$k=$v")->implode('  '));
        $this->output->writeln('Laporan  : '.$path);

        return self::SUCCESS;
    }

    private function sec(string $title): void
    {
        $this->secNo++;
        $this->body[] = '';
        $this->body[] = str_repeat('=', 78);
        $this->body[] = $this->secNo.'. '.mb_strtoupper($title);
        $this->body[] = str_repeat('=', 78);
    }

    private function mark(string $level, string $text): void
    {
        $this->tally[$level]++;
        $this->body[] = sprintf('[%-4s] %s', $level, $text);
    }

    private function note(string $text): void
    {
        $this->body[] = '       '.$text;
    }

    private function safe(\Throwable $e): string
    {
        $m = preg_replace('/(password|passwd|secret|token|api[_-]?key)\s*[=:]\s*\S+/i', '$1=***', $e->getMessage());
        $m = Str::limit(preg_replace('/\s+/', ' ', (string) $m), 300);

        return get_class($e).': '.$m;
    }

    private function rel(string $p): string
    {
        return Str::after($p, base_path().DIRECTORY_SEPARATOR);
    }

    private function probe(string $label, $query, string $level = 'FAIL', string $idCol = 'id'): void
    {
        $n = (clone $query)->count();
        if ($n === 0) {
            $this->mark('PASS', $label);

            return;
        }
        $ids = (clone $query)->limit(10)->pluck($idCol)->all();
        $this->mark($level, "$label: $n temuan (contoh id: ".implode(',', $ids).')');
    }

    private function sameSet(string $label, array $vis, array $exp): void
    {
        $extra = array_values(array_diff($vis, $exp));
        $miss = array_values(array_diff($exp, $vis));
        if (! $extra && ! $miss) {
            $this->mark('PASS', "$label: ".count($vis).' baris sesuai harapan');

            return;
        }
        $this->mark('FAIL', "$label: bocor=".count($extra).' (contoh '.implode(',', array_slice($extra, 0, 5)).') tersembunyi='.count($miss).' (contoh '.implode(',', array_slice($miss, 0, 5)).')');
    }

    private function live()
    {
        $q = DB::table('customers');

        return Schema::hasColumn('customers', 'deleted_at') ? $q->whereNull('customers.deleted_at') : $q;
    }

    // ------------------------------------------------------------------ 1
    private function sectionEnvironment(): void
    {
        $this->mark('INFO', 'PHP '.PHP_VERSION.' | Laravel '.app()->version().' | tz='.config('app.timezone'));
        $debug = (bool) config('app.debug');
        $this->mark($debug ? 'WARN' : 'PASS', 'APP_DEBUG='.($debug ? 'true (wajib false di produksi)' : 'false').' | APP_ENV='.config('app.env'));
        $this->mark(filled(config('app.key')) ? 'PASS' : 'FAIL', 'APP_KEY '.(filled(config('app.key')) ? 'terisi' : 'KOSONG'));
        $this->mark('INFO', 'DB='.config('database.default').' driver='.DB::connection()->getDriverName());
        $this->mark('INFO', 'WILAYAH_ENFORCE='.(config('wilayah.enforce') ? 'true' : 'false'));
        $this->mark('INFO', 'session driver='.config('session.driver').' secure='.var_export(config('session.secure'), true).' same_site='.var_export(config('session.same_site'), true).' lifetime='.config('session.lifetime').'m');
        $this->mark('INFO', 'cache='.config('cache.default').' queue='.config('queue.default'));
        $free = @disk_free_space(base_path());
        $this->mark('INFO', 'Ruang disk bebas: '.($free ? round($free / 1073741824, 1).' GB' : '?'));

        Artisan::call('migrate:status');
        $out = Artisan::output();
        $pending = collect(explode("\n", $out))->filter(fn ($l) => str_contains($l, 'Pending'))->count();
        $this->mark($pending ? 'FAIL' : 'PASS', $pending ? "$pending migrasi belum dijalankan" : 'Semua migrasi sudah dijalankan');

        foreach (['storage', 'bootstrap/cache'] as $d) {
            $this->mark(is_writable(base_path($d)) ? 'PASS' : 'FAIL', "$d dapat ditulis");
        }

        Artisan::call('schedule:list');
        foreach (array_filter(array_map('trim', explode("\n", Artisan::output()))) as $l) {
            $this->note('jadwal: '.$l);
        }
    }

    // ------------------------------------------------------------------ 2
    private function sectionLint(): void
    {
        $php = escapeshellarg(PHP_BINARY);
        $files = 0;
        $bad = 0;
        foreach (['app', 'routes', 'database', 'config', 'bootstrap', 'tests'] as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile() || $f->getExtension() !== 'php') {
                    continue;
                }
                $files++;
                $out = [];
                exec($php.' -l '.escapeshellarg($f->getPathname()).' 2>&1', $out, $code);
                if ($code !== 0) {
                    $bad++;
                    $this->mark('FAIL', 'Sintaks PHP '.$this->rel($f->getPathname()).': '.Str::limit(implode(' ', $out), 200));
                }
            }
        }
        if ($bad === 0) {
            $this->mark('PASS', "$files berkas PHP lolos php -l");
        }

        $views = 0;
        $badViews = 0;
        $vpath = resource_path('views');
        if (is_dir($vpath)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($vpath, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                    continue;
                }
                $views++;
                try {
                    $compiled = Blade::compileString(file_get_contents($f->getPathname()));
                    $tmp = tempnam(sys_get_temp_dir(), 'bld');
                    file_put_contents($tmp, $compiled);
                    $out = [];
                    exec($php.' -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
                    @unlink($tmp);
                    if ($code !== 0) {
                        $badViews++;
                        $this->mark('FAIL', 'Blade '.$this->rel($f->getPathname()).': '.Str::limit(implode(' ', $out), 200));
                    }
                } catch (\Throwable $e) {
                    $badViews++;
                    $this->mark('FAIL', 'Blade '.$this->rel($f->getPathname()).': '.$this->safe($e));
                }
            }
        }
        if ($badViews === 0) {
            $this->mark('PASS', "$views berkas Blade berhasil dikompilasi dan lolos php -l");
        }
    }

    // ------------------------------------------------------------------ 3
    private function sectionSchema(): void
    {
        $skip = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations', 'sqlite_sequence'];
        $names = collect(Schema::getTables())->map(fn ($t) => is_array($t) ? $t['name'] : $t->name)->sort()->values();
        $noFk = [];
        foreach ($names as $t) {
            $count = DB::table($t)->count();
            $cols = collect(Schema::getColumns($t));
            $this->note(sprintf('%s (%d baris)', $t, $count));
            $this->note('   kolom: '.$cols->map(fn ($c) => $c['name'].':'.($c['type_name'] ?? $c['type'] ?? '?').(! empty($c['nullable']) ? '?' : ''))->implode(', '));
            $fkCols = [];
            foreach (Schema::getForeignKeys($t) as $fk) {
                $this->note('   FK '.implode(',', $fk['columns']).' -> '.$fk['foreign_table'].'('.implode(',', $fk['foreign_columns']).') hapus='.($fk['on_delete'] ?? '?'));
                foreach ($fk['columns'] as $c) {
                    $fkCols[$c] = true;
                }
            }
            if (in_array($t, $skip, true)) {
                continue;
            }
            foreach ($cols as $c) {
                if (Str::endsWith($c['name'], '_id') && empty($fkCols[$c['name']])) {
                    $noFk[] = $t.'.'.$c['name'];
                }
            }
        }
        $this->mark('INFO', $names->count().' tabel dicatat');
        $this->mark($noFk ? 'WARN' : 'PASS', 'Kolom *_id tanpa foreign key: '.($noFk ? implode(', ', $noFk) : 'tidak ada'));
    }

    // ------------------------------------------------------------------ 4
    private function sectionRoutes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $this->mark('INFO', $routes->count().' rute terdaftar');
        $noAuth = [];
        $mutNoGate = [];
        $broken = 0;
        foreach ($routes as $r) {
            $mw = collect($r->gatherMiddleware())->filter(fn ($m) => is_string($m))->values();
            $methods = implode('|', array_diff($r->methods(), ['HEAD']));
            $gates = $mw->filter(fn ($m) => str_starts_with($m, 'can:'))->implode(',');
            $hasAuth = $mw->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:'));
            $this->note(sprintf('%-12s /%s [%s] %s mw=%s', $methods, ltrim($r->uri(), '/'), $r->getName() ?? '-', $r->getActionName(), $mw->implode(',')));
            $action = $r->getActionName();
            if ($action !== 'Closure') {
                [$cls, $m] = array_pad(explode('@', $action), 2, '__invoke');
                if (! class_exists($cls) || ! method_exists($cls, $m)) {
                    $broken++;
                    $this->mark('FAIL', "Rute /{$r->uri()} menunjuk ke $action yang tidak ada");
                }
            }
            if (! $hasAuth) {
                $noAuth[] = $methods.' /'.ltrim($r->uri(), '/');
            } elseif ($gates === '' && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $r->methods())) {
                $mutNoGate[] = $methods.' /'.ltrim($r->uri(), '/');
            }
        }
        if ($broken === 0) {
            $this->mark('PASS', 'Semua aksi rute menunjuk ke kelas dan method yang ada');
        }
        $this->mark('INFO', 'Rute tanpa middleware auth ('.count($noAuth).'): '.implode(' ; ', $noAuth));
        $this->mark('INFO', 'Rute tulis dengan auth tetapi tanpa gate can: ('.count($mutNoGate).'): '.implode(' ; ', $mutNoGate));
    }

    // ------------------------------------------------------------------ 5
    private function sectionCustomers(): void
    {
        $total = DB::table('customers')->count();
        $del = Schema::hasColumn('customers', 'deleted_at') ? DB::table('customers')->whereNotNull('deleted_at')->count() : 0;
        $this->mark('INFO', "Pelanggan: total=$total terhapus(arsip)=$del");
        foreach (['service_status', 'service_type', 'first_billing_mode', 'price_mode', 'wilayah_id'] as $col) {
            if (Schema::hasColumn('customers', $col)) {
                $d = $this->live()->selectRaw("$col as k, count(*) as c")->groupBy($col)->get()->map(fn ($r) => ($r->k ?? 'null').'='.$r->c)->implode(' ');
                $this->mark('INFO', "Sebaran $col (aktif): $d");
            }
        }

        $this->probe('PPPoE tanpa username', $this->live()->where('service_type', 'pppoe')->whereNull('pppoe_username'), 'FAIL', 'customers.id');
        if (Schema::hasColumn('customers', 'static_ip')) {
            $this->probe('Static tanpa IP', $this->live()->where('service_type', 'static')->whereNull('static_ip'), 'FAIL', 'customers.id');
        }

        $dups = DB::table('customers')->whereNotNull('pppoe_username')->selectRaw('network_router_id, pppoe_username, count(*) c')->groupBy('network_router_id', 'pppoe_username')->havingRaw('count(*) > 1')->get();
        $this->mark($dups->isEmpty() ? 'PASS' : 'FAIL', 'Username PPPoE ganda per router: '.$dups->count());

        $this->probe('PPPoE dengan paket yang tidak dipetakan ke routernya', $this->live()->where('service_type', 'pppoe')->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('internet_package_router')
                ->whereColumn('internet_package_router.internet_package_id', 'customers.internet_package_id')
                ->whereColumn('internet_package_router.network_router_id', 'customers.network_router_id');
        }), 'FAIL', 'customers.id');

        $this->probe('wilayah_id kosong atau tidak ada', $this->live()->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('wilayah')->whereColumn('wilayah.id', 'customers.wilayah_id');
        }), 'FAIL', 'customers.id');

        $this->probe('Router pelanggan tidak melayani wilayah pelanggan', $this->live()->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('network_router_wilayah')
                ->whereColumn('network_router_wilayah.network_router_id', 'customers.network_router_id')
                ->whereColumn('network_router_wilayah.wilayah_id', 'customers.wilayah_id');
        }), 'WARN', 'customers.id');

        $this->probe('Harga paket kosong', $this->live()->where('price_mode', 'package')->whereExists(function ($q) {
            $q->select(DB::raw(1))->from('internet_packages')->whereColumn('internet_packages.id', 'customers.internet_package_id')->whereNull('internet_packages.monthly_price');
        }), 'WARN', 'customers.id');

        if (Schema::hasColumn('customers', 'invoice_day')) {
            $this->probe('invoice_day kosong', $this->live()->whereNull('invoice_day'), 'WARN', 'customers.id');
        }
        $this->probe('started_at kosong', $this->live()->whereNull('started_at'), 'WARN', 'customers.id');

        if ($del > 0) {
            $this->probe('Arsip masih memegang username/IP (harus null)', DB::table('customers')->whereNotNull('deleted_at')->where(function ($q) {
                $q->whereNotNull('pppoe_username');
                if (Schema::hasColumn('customers', 'static_ip')) {
                    $q->orWhereNotNull('static_ip');
                }
            }));
        }

        if (Schema::hasTable('customer_network_actions')) {
            $d = DB::table('customer_network_actions')->selectRaw('kind, status, count(*) c')->groupBy('kind', 'status')->get()->map(fn ($r) => "{$r->kind}/{$r->status}={$r->c}")->implode(' ');
            $this->mark('INFO', 'Aksi jaringan: '.($d ?: 'kosong'));
            $this->probe('Aksi status akhir tetapi masih memegang kunci terbuka', DB::table('customer_network_actions')->whereNotNull('open_customer_id')->whereNotIn('status', ['reserved', 'sending', 'uncertain']));
            $this->probe('Aksi reserved/sending/uncertain tanpa kunci terbuka', DB::table('customer_network_actions')->whereNull('open_customer_id')->whereIn('status', ['reserved', 'sending', 'uncertain']));
            $this->probe('Aksi reserved/sending macet >10 menit', DB::table('customer_network_actions')->whereIn('status', ['reserved', 'sending'])->where('updated_at', '<', now()->subMinutes(10)), 'WARN');
            $this->probe('Aksi untuk pelanggan yang sudah diarsipkan masih terbuka', DB::table('customer_network_actions')->whereNotNull('open_customer_id')->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('customers')->whereColumn('customers.id', 'customer_network_actions.customer_id')->whereNotNull('customers.deleted_at');
            }), 'FAIL');
        }

        if (Schema::hasTable('customer_billing_pauses')) {
            $this->mark('INFO', 'Jeda tagihan: total='.DB::table('customer_billing_pauses')->count().' terbuka='.DB::table('customer_billing_pauses')->whereNotNull('open_customer_id')->count());
            $this->probe('Jeda selesai sebelum mulai', DB::table('customer_billing_pauses')->whereColumn('resumed_on', '<', 'paused_from'));
            $this->probe('Jeda terbuka tetapi punya tanggal selesai', DB::table('customer_billing_pauses')->whereNotNull('open_customer_id')->whereNotNull('resumed_on'));
            $this->probe('Jeda tertutup tanpa tanggal selesai', DB::table('customer_billing_pauses')->whereNull('open_customer_id')->whereNull('resumed_on'));
        }
    }

    // ------------------------------------------------------------------ 6
    private function sectionAccounting(): void
    {
        $it = (new Invoice)->getTable();
        $rt = (new BillingReceipt)->getTable();
        $bt = (new BillingBalanceEntry)->getTable();
        $ic = Schema::getColumnListing($it);
        $this->mark('INFO', "Tabel tagihan=$it kolom: ".implode(', ', $ic));

        if (in_array('status', $ic) && in_array('total', $ic) && in_array('paid_amount', $ic)) {
            foreach (DB::table($it)->selectRaw('status, count(*) c, sum(total) t, sum(paid_amount) p')->groupBy('status')->get() as $s) {
                $this->mark('INFO', "Tagihan status={$s->status}: jumlah={$s->c} total={$s->t} terbayar={$s->p}");
            }
            $this->probe('Terbayar melebihi total', DB::table($it)->whereColumn('paid_amount', '>', 'total'));
            $this->probe('Nilai negatif', DB::table($it)->where(fn ($q) => $q->where('paid_amount', '<', 0)->orWhere('total', '<', 0)));
            $this->probe('Status unpaid tetapi sudah ada pembayaran', DB::table($it)->where('status', 'unpaid')->where('paid_amount', '>', 0));
            $this->probe('Status partial tidak konsisten', DB::table($it)->where('status', 'partial')->where(fn ($q) => $q->where('paid_amount', '<=', 0)->orWhereColumn('paid_amount', '>=', 'total')));
            $this->probe('Status paid tetapi terbayar tidak sama total', DB::table($it)->where('status', 'paid')->whereColumn('paid_amount', '<>', 'total'));
            $this->probe('Status void tetapi ada pembayaran', DB::table($it)->where('status', 'void')->where('paid_amount', '>', 0), 'WARN');
        } else {
            $this->mark('SKIP', 'Kolom status/total/paid_amount tidak lengkap');
        }
        if (in_array('net', $ic) && in_array('tax', $ic)) {
            $this->probe('total tidak sama net + tax', DB::table($it)->whereRaw('total <> net + tax'), 'WARN');
        }
        if (in_array('number', $ic)) {
            $d = DB::table($it)->whereNotNull('number')->selectRaw('number, count(*) c')->groupBy('number')->havingRaw('count(*) > 1')->get();
            $this->mark($d->isEmpty() ? 'PASS' : 'FAIL', 'Nomor tagihan ganda: '.$d->count());
        }
        if (in_array('active_period_key', $ic)) {
            $d = DB::table($it)->whereNotNull('active_period_key')->selectRaw('active_period_key, count(*) c')->groupBy('active_period_key')->havingRaw('count(*) > 1')->get();
            $this->mark($d->isEmpty() ? 'PASS' : 'FAIL', 'active_period_key ganda: '.$d->count());
        }
        if (in_array('period_start', $ic) && in_array('period_end', $ic)) {
            $this->probe('period_end sebelum period_start', DB::table($it)->whereColumn('period_end', '<', 'period_start'));
            if (in_array('customer_id', $ic)) {
                $ov = DB::select("select a.id as a, b.id as b from $it a join $it b on a.customer_id=b.customer_id and a.id<b.id and a.period_start<=b.period_end and b.period_start<=a.period_end where a.status<>'void' and b.status<>'void' limit 10");
                $this->mark($ov ? 'FAIL' : 'PASS', 'Periode tagihan tumpang tindih per pelanggan: '.($ov ? json_encode($ov) : '0'));
            }
        }
        if (in_array('issued_on', $ic) && in_array('due_on', $ic)) {
            $this->probe('Jatuh tempo sebelum tanggal terbit', DB::table($it)->whereColumn('due_on', '<', 'issued_on'));
        }

        $this->orphans($it, 'customer_id', 'customers');
        $rc = Schema::getColumnListing($rt);
        $bc = Schema::getColumnListing($bt);
        $this->mark('INFO', "Tabel kuitansi=$rt kolom: ".implode(', ', $rc));
        $this->mark('INFO', "Tabel saldo=$bt kolom: ".implode(', ', $bc));
        $this->orphans($rt, 'customer_id', 'customers');
        $this->orphans($rt, 'invoice_id', $it);
        $this->orphans($bt, 'customer_id', 'customers');
        $this->orphans($bt, 'invoice_id', $it);
        $this->orphans($bt, 'receipt_id', $rt);

        if (in_array('amount', $rc)) {
            $grp = array_values(array_intersect(['kind', 'status'], $rc));
            if ($grp) {
                foreach (DB::table($rt)->selectRaw(implode(',', $grp).', count(*) c, sum(amount) s')->groupBy($grp)->get() as $r) {
                    $this->mark('INFO', 'Kuitansi '.collect($grp)->map(fn ($g) => "$g=".($r->$g ?? 'null'))->implode(' ')." jumlah={$r->c} nilai={$r->s}");
                }
            }
            $this->probe('Kuitansi dengan nilai <= 0', DB::table($rt)->where('amount', '<=', 0), 'WARN');
        }
        if (in_array('amount', $bc)) {
            $grp = in_array('status', $bc) ? ['status'] : [];
            $q = DB::table($bt)->selectRaw(($grp ? 'status, ' : '').'count(*) c, sum(amount) s');
            foreach (($grp ? $q->groupBy('status') : $q)->get() as $r) {
                $this->mark('INFO', 'Mutasi saldo '.($grp ? 'status='.($r->status ?? 'null') : '').": jumlah={$r->c} nilai={$r->s}");
            }
            if (in_array('customer_id', $bc)) {
                $neg = DB::table($bt)->when(in_array('status', $bc), fn ($q) => $q->where('status', 'active'))->selectRaw('customer_id, sum(amount) s')->groupBy('customer_id')->havingRaw('sum(amount) < 0')->get();
                $this->mark($neg->isEmpty() ? 'PASS' : 'FAIL', 'Saldo pelanggan negatif: '.$neg->count().($neg->isEmpty() ? '' : ' (id: '.$neg->pluck('customer_id')->take(10)->implode(',').')'));
            }
        }
        if (in_array('total', $ic) && in_array('paid_amount', $ic)) {
            $this->mark('INFO', 'Pembanding: sum(paid_amount) tagihan non-void = '.DB::table($it)->where('status', '<>', 'void')->sum('paid_amount').' (cocokkan manual dengan total kuitansi aktif dan mutasi saldo di atas)');
        }

        if (Schema::hasTable('expenses')) {
            $ec = Schema::getColumnListing('expenses');
            $this->mark('INFO', 'Pengeluaran: '.DB::table('expenses')->count().' baris, kolom: '.implode(', ', $ec));
            if (in_array('amount', $ec)) {
                $this->mark('INFO', 'Total pengeluaran='.DB::table('expenses')->sum('amount'));
                $this->probe('Pengeluaran bernilai <= 0', DB::table('expenses')->where('amount', '<=', 0), 'WARN');
            }
        }
    }

    private function orphans(string $table, string $col, string $ref): void
    {
        if (! Schema::hasColumn($table, $col)) {
            return;
        }
        $q = DB::table($table)->whereNotNull($col)->whereNotExists(function ($q) use ($table, $col, $ref) {
            $q->select(DB::raw(1))->from($ref)->whereColumn("$ref.id", "$table.$col");
        });
        $this->probe("Yatim: $table.$col tidak ada di $ref", $q);
    }

    // ------------------------------------------------------------------ 7
    private function sectionWilayah(): void
    {
        $users = User::orderBy('id')->get();
        foreach ($users as $u) {
            $g = collect(['access-billing', 'manage-users', 'manage-system'])->map(fn ($x) => $x.'='.(Gate::forUser($u)->allows($x) ? 'ya' : 'tidak'))->implode(' ');
            $w = $u->isSuperAdmin() ? '(semua)' : json_encode($u->accessibleWilayahIds());
            $this->mark('INFO', "user #{$u->id} {$u->name} peran={$u->role} wilayah=$w $g");
        }

        $prev = config('wilayah.enforce');
        config(['wilayah.enforce' => true]);
        try {
            $targets = [
                ['invoices', Invoice::class, (new Invoice)->getTable()],
                ['kuitansi', BillingReceipt::class, (new BillingReceipt)->getTable()],
                ['mutasi saldo', BillingBalanceEntry::class, (new BillingBalanceEntry)->getTable()],
            ];
            foreach ($users as $u) {
                Auth::guard()->setUser($u);
                $allowed = WilayahScope::allowedIds();
                if ($allowed === null) {
                    $vis = Customer::count();
                    $raw = $this->live()->count();
                    $this->mark($vis === $raw ? 'PASS' : 'FAIL', "#{$u->id} (tanpa batas) pelanggan terlihat=$vis mentah=$raw");

                    continue;
                }
                $this->sameSet("#{$u->id} pelanggan", Customer::pluck('id')->all(), $this->live()->whereIn('wilayah_id', $allowed)->pluck('customers.id')->all());
                foreach ($targets as [$label, $cls, $tbl]) {
                    if (! Schema::hasColumn($tbl, 'customer_id')) {
                        continue;
                    }
                    $exp = DB::table($tbl)->join('customers', 'customers.id', '=', "$tbl.customer_id")->whereIn('customers.wilayah_id', $allowed)->pluck("$tbl.id")->all();
                    $this->sameSet("#{$u->id} $label", $cls::pluck('id')->all(), $exp);
                }
            }
        } finally {
            config(['wilayah.enforce' => $prev]);
            Auth::guard()->forgetUser();
        }
    }

    // ------------------------------------------------------------------ 8
    private function sampleId(string $p)
    {
        $map = [
            'customer' => Customer::class, 'invoice' => Invoice::class, 'receipt' => BillingReceipt::class,
            'entry' => BillingBalanceEntry::class, 'router' => NetworkRouter::class, 'package' => InternetPackage::class,
        ];
        $class = $map[$p] ?? 'App\\Models\\'.Str::studly($p);
        if (! class_exists($class)) {
            return null;
        }

        return (new $class)->newQuery()->orderBy('id')->value('id');
    }

    private function fillUri($r): ?string
    {
        $uri = $r->uri();
        foreach ($r->parameterNames() as $p) {
            $id = $this->sampleId($p);
            if ($id === null) {
                return null;
            }
            $uri = preg_replace('/\{'.preg_quote($p, '/').'(:[^}]*)?\??\}/', (string) $id, $uri);
        }

        return '/'.ltrim($uri, '/');
    }

    private function sectionSmoke(): void
    {
        $prevEnforce = config('wilayah.enforce');
        $prevSession = config('session.driver');
        config(['session.driver' => 'array', 'wilayah.enforce' => true]);
        $kernel = app(Kernel::class);

        $actors = [];
        foreach (User::orderBy('id')->get() as $u) {
            $actors[($u->isSuperAdmin() ? 'super#' : 'admin#').$u->id] = $u;
        }
        $actors['guest'] = null;

        $skipName = '/^(logout|login|register|password\.|verification\.|livewire|sanctum|storage|ignition|debugbar)/';
        $withNet = (bool) $this->option('router');
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()))
            ->reject(fn ($r) => preg_match($skipName, (string) $r->getName()) || str_starts_with($r->uri(), '_') || str_starts_with($r->uri(), 'flux/') || (! $withNet && str_contains((string) $r->getName(), 'network')));

        $matrix = [];
        $stat = [];
        try {
            foreach ($routes as $r) {
                $key = '/'.ltrim($r->uri(), '/').' ['.($r->getName() ?? '-').']';
                $mw = collect($r->gatherMiddleware())->filter(fn ($m) => is_string($m));
                $hasAuth = $mw->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:'));
                $gates = $mw->filter(fn ($m) => str_starts_with($m, 'can:'))->map(fn ($m) => explode(',', substr($m, 4))[0])->filter(fn ($g) => Gate::has($g))->values();

                foreach ($actors as $label => $user) {
                    if ($user) {
                        Auth::guard()->setUser($user);
                    } else {
                        Auth::guard()->forgetUser();
                    }
                    $uri = $this->fillUri($r);
                    if ($uri === null) {
                        $matrix[$key][$label] = 'n/a';

                        continue;
                    }
                    DB::flushQueryLog();
                    DB::enableQueryLog();
                    $t = microtime(true);
                    $exc = null;
                    try {
                        $response = $kernel->handle(Request::create($uri, 'GET'));
                        $status = $response->getStatusCode();
                        $exc = $response->exception ?? null;
                    } catch (\Throwable $e) {
                        $status = 599;
                        $exc = $e;
                    }
                    $ms = (microtime(true) - $t) * 1000;
                    $q = count(DB::getQueryLog());
                    DB::disableQueryLog();

                    $matrix[$key][$label] = (string) $status;
                    $stat[$status] = ($stat[$status] ?? 0) + 1;

                    if ($status >= 500) {
                        $where = $exc ? ' @ '.$this->rel($exc->getFile()).':'.$exc->getLine() : '';
                        $this->mark('FAIL', "$label GET $uri -> $status ".($exc ? $this->safe($exc) : '').$where);
                    }
                    if ($ms > 2000) {
                        $this->mark('WARN', sprintf('%s GET %s lambat: %.0f ms', $label, $uri, $ms));
                    }
                    if ($q > 80) {
                        $this->mark('WARN', "$label GET $uri memakai $q query (curiga N+1)");
                    }
                    if ($user === null && $hasAuth && $status === 200) {
                        $this->mark('FAIL', "GET $uri terbuka tanpa login padahal memakai auth");
                    }
                    if ($user) {
                        $allowed = $gates->every(fn ($g) => Gate::forUser($user)->allows($g));
                        if (! $allowed && $status === 200) {
                            $this->mark('FAIL', "$label GET $uri terbuka padahal gate ".$gates->implode(',').' menolak');
                        }
                        if ($allowed && $status === 403) {
                            $this->mark('WARN', "$label GET $uri ditolak 403 padahal gate mengizinkan");
                        }
                    }
                }
            }
        } finally {
            config(['wilayah.enforce' => $prevEnforce, 'session.driver' => $prevSession]);
            Auth::guard()->forgetUser();
        }

        ksort($stat);
        $this->mark('INFO', 'Sebaran status HTTP: '.collect($stat)->map(fn ($v, $k) => "$k=$v")->implode(' '));
        $this->note('Matriks status per rute dan pengguna:');
        foreach ($matrix as $key => $cols) {
            $this->note($key.'  '.collect($cols)->map(fn ($s, $l) => "$l=$s")->implode(' '));
        }
    }

    // ------------------------------------------------------------------ 9
    private function sectionSecurity(): void
    {
        $enc = fn ($v) => is_string($v) && str_starts_with($v, 'eyJpdiI');
        foreach ([['network_routers', 'password'], ['customers', 'pppoe_password']] as [$t, $c]) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) {
                continue;
            }
            $vals = DB::table($t)->whereNotNull($c)->pluck($c);
            $plain = $vals->reject($enc)->count();
            $this->mark($plain ? 'FAIL' : 'PASS', "$t.$c: {$vals->count()} terisi, $plain tersimpan sebagai teks biasa");
        }
        $badHash = User::pluck('password')->reject(fn ($h) => is_string($h) && (str_starts_with($h, '$2y$') || str_starts_with($h, '$argon')))->count();
        $this->mark($badHash ? 'FAIL' : 'PASS', "Kata sandi pengguna non-hash: $badHash");

        $env = base_path('.env');
        if (is_file($env)) {
            $mode = fileperms($env) & 0777;
            $this->mark(($mode & 0004) ? 'WARN' : 'PASS', sprintf('Izin .env = %04o', $mode));
        }

        $dir = storage_path('app/backups');
        if (is_dir($dir)) {
            $n = 0;
            $size = 0;
            $envCopies = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (! $f->isFile()) {
                    continue;
                }
                $n++;
                $size += $f->getSize();
                if (str_contains($f->getFilename(), '.env') || str_contains($f->getPathname(), '.env')) {
                    $envCopies[] = $this->rel($f->getPathname());
                }
            }
            $this->mark('INFO', "storage/app/backups: $n berkas, ".round($size / 1048576, 1).' MB');
            $this->mark($envCopies ? 'WARN' : 'PASS', 'Salinan .env di folder cadangan: '.count($envCopies).($envCopies ? ' (berisi rahasia; jangan ikut deploy/commit)' : ''));
        }

        $bad = [];
        foreach (glob(public_path('{,.}*'), GLOB_BRACE) ?: [] as $p) {
            if (preg_match('/(^\.env|\.sql$|\.sqlite$|\.bak$|^\.git$|backup)/i', basename($p))) {
                $bad[] = basename($p);
            }
        }
        $this->mark($bad ? 'FAIL' : 'PASS', 'Berkas sensitif di public/: '.($bad ? implode(', ', $bad) : 'tidak ada'));

        foreach (['bootstrap/app.php', 'app/Http/Middleware/VerifyCsrfToken.php'] as $f) {
            if (is_file(base_path($f))) {
                foreach (file(base_path($f)) as $i => $l) {
                    if (preg_match('/csrf|\$except/i', $l)) {
                        $this->note("CSRF $f:".($i + 1).': '.trim($l));
                    }
                }
            }
        }

        if (is_file(base_path('phpunit.xml'))) {
            foreach (file(base_path('phpunit.xml')) as $l) {
                if (str_contains($l, 'DB_')) {
                    $this->note('phpunit: '.trim($l));
                }
            }
            $mem = str_contains((string) file_get_contents(base_path('phpunit.xml')), ':memory:');
            $this->mark($mem ? 'PASS' : 'WARN', 'phpunit '.($mem ? 'memakai database :memory:' : 'TIDAK memakai :memory: - jangan jalankan php artisan test sebelum dicek (RefreshDatabase bisa menghapus data)'));
        }
        $tests = is_dir(base_path('tests')) ? collect(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('tests'), \FilesystemIterator::SKIP_DOTS))))->filter(fn ($f) => str_ends_with($f->getFilename(), 'Test.php'))->count() : 0;
        $this->mark('INFO', "Berkas pengujian otomatis: $tests");
    }

    // ------------------------------------------------------------------ 10
    private function sectionCodeScan(): void
    {
        $rules = [
            'Blade tanpa escape {!! !!}' => ['/\{!!/', 'views'],
            'SQL mentah' => ['/\b(DB::raw|whereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|DB::statement|DB::unprepared)\s*\(/', 'app'],
            'env() di luar config' => ['/\benv\s*\(/', 'app'],
            'Sisa debug' => ['/\b(dd|dump|var_dump|print_r|ray)\s*\(/', 'app'],
            '$request->all()' => ['/\$request->all\(\)/', 'app'],
            'Model unguard/$guarded kosong' => ['/unguard\(|\$guarded\s*=\s*\[\s*\]/', 'app'],
            'forceDelete' => ['/forceDelete\s*\(/', 'app'],
            'Log memuat password' => ['/Log::.*password/i', 'app'],
            'catch menelan error' => ['/catch\s*\(\\\\?Throwable\s+\$ignored\)/', 'app'],
        ];
        $hits = array_fill_keys(array_keys($rules), []);
        foreach (['app' => base_path('app'), 'views' => resource_path('views')] as $scope => $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (! $f->isFile() || ! preg_match('/\.php$/', $f->getFilename()) || $f->getFilename() === 'AuditFull.php') {
                    continue;
                }
                foreach (file($f->getPathname()) as $i => $line) {
                    foreach ($rules as $name => [$re, $sc]) {
                        $applies = $sc === $scope || ($name === 'env() di luar config' && $scope === 'views');
                        if ($applies && preg_match($re, $line)) {
                            $hits[$name][] = $this->rel($f->getPathname()).':'.($i + 1);
                        }
                    }
                }
            }
        }
        foreach ($hits as $name => $list) {
            $this->mark($list ? 'INFO' : 'PASS', "$name: ".count($list).' kemunculan'.($list ? ' -> '.implode(' ; ', array_slice($list, 0, 15)) : ''));
        }
    }

    // ------------------------------------------------------------------ opsional: router
    private function sectionRouter(): void
    {
        $reader = app(RouterSecretReader::class);
        foreach (NetworkRouter::orderBy('id')->get() as $router) {
            $label = 'router #'.$router->id.' '.($router->name ?? '');
            try {
                $t = microtime(true);
                $rows = collect($reader->read($router));
            } catch (\Throwable $e) {
                $this->mark('FAIL', "$label tidak terbaca: ".$this->safe($e));

                continue;
            }
            $this->mark('PASS', sprintf('%s: %d secret terbaca (%.1fs)', $label, $rows->count(), microtime(true) - $t));
            $by = $rows->keyBy(fn ($r) => is_array($r) ? ($r['username'] ?? $r['name'] ?? '') : ($r->username ?? $r->name ?? ''));
            $customers = $this->live()->where('network_router_id', $router->id)->where('service_type', 'pppoe')->whereNotNull('pppoe_username')
                ->get(['customers.id', 'pppoe_username', 'service_status', 'internet_package_id']);
            $normal = DB::table('internet_package_router')->where('network_router_id', $router->id)->get()->keyBy('internet_package_id');
            $paused = DB::table('customer_billing_pauses')->whereNotNull('open_customer_id')->pluck('open_customer_id')->flip();

            $missing = [];
            $mismatch = [];
            $disabled = [];
            foreach ($customers as $c) {
                $row = $by->get($c->pppoe_username);
                if (! $row) {
                    if ($c->service_status === 'active') {
                        $missing[] = $c->pppoe_username;
                    }

                    continue;
                }
                $row = (array) $row;
                $expected = $paused->has($c->id) ? ($router->isolation_profile ?? null) : ($normal->get($c->internet_package_id)->normal_profile ?? null);
                $actual = $row['profile'] ?? null;
                if ($expected && $actual && $expected !== $actual) {
                    $mismatch[] = "{$c->pppoe_username}(harap=$expected,aktual=$actual)";
                }
                if (! empty($row['disabled']) && $row['disabled'] !== 'false' && $c->service_status === 'active') {
                    $disabled[] = $c->pppoe_username;
                }
            }
            $known = $customers->pluck('pppoe_username')->all();
            $extra = $by->keys()->reject(fn ($u) => in_array($u, $known, true))->values()->all();

            $this->mark($missing ? 'FAIL' : 'PASS', "$label pelanggan aktif tanpa secret di router: ".count($missing).($missing ? ' ('.implode(',', array_slice($missing, 0, 15)).')' : ''));
            $this->mark($extra ? 'WARN' : 'PASS', "$label secret di router tanpa pelanggan billing: ".count($extra).($extra ? ' ('.implode(',', array_slice($extra, 0, 15)).')' : ''));
            $this->mark($mismatch ? 'WARN' : 'PASS', "$label profil tidak sesuai: ".count($mismatch).($mismatch ? ' ('.implode(' ; ', array_slice($mismatch, 0, 10)).')' : ''));
            $this->mark($disabled ? 'WARN' : 'PASS', "$label pelanggan aktif tetapi secret disabled: ".count($disabled).($disabled ? ' ('.implode(',', array_slice($disabled, 0, 10)).')' : ''));
        }
    }

    // ------------------------------------------------------------------ opsional: skenario
    private function sectionScenario(): void
    {
        $tables = ['customers', (new Invoice)->getTable(), (new BillingReceipt)->getTable(), (new BillingBalanceEntry)->getTable(), 'customer_network_actions', 'customer_billing_pauses'];
        $snap = fn () => array_map(fn ($t) => DB::table($t)->count(), $tables);
        $before = $snap();
        $prev = config('wilayah.enforce');
        DB::beginTransaction();
        try {
            $this->scenarioBody();
        } catch (\Throwable $e) {
            $this->mark('FAIL', 'Skenario berhenti: '.$this->safe($e).' @ '.$this->rel($e->getFile()).':'.$e->getLine());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            config(['wilayah.enforce' => $prev]);
            Auth::guard()->forgetUser();
        }
        $after = $snap();
        $this->mark($before === $after ? 'PASS' : 'FAIL', 'Rollback skenario: sebelum='.json_encode($before).' sesudah='.json_encode($after));
    }

    private function scenarioBody(): void
    {
        $users = User::orderBy('id')->get();
        $super = $users->first(fn ($u) => $u->isSuperAdmin());
        $admin = $users->first(fn ($u) => ! $u->isSuperAdmin() && ! empty($u->accessibleWilayahIds()));
        if (! $super) {
            $this->mark('SKIP', 'Tidak ada super admin');

            return;
        }
        $map = DB::table('internet_package_router')
            ->join('internet_packages', 'internet_packages.id', '=', 'internet_package_router.internet_package_id')
            ->where('internet_packages.is_active', true)->where('internet_packages.service_type', 'pppoe')
            ->select('internet_package_router.internet_package_id as package_id', 'internet_package_router.network_router_id as router_id')->first();
        if (! $map) {
            $this->mark('SKIP', 'Tidak ada pasangan paket PPPoE aktif dan router');

            return;
        }
        $wid = null;
        if ($admin) {
            $wid = DB::table('network_router_wilayah')->where('network_router_id', $map->router_id)->whereIn('wilayah_id', $admin->accessibleWilayahIds())->value('wilayah_id');
        }
        $wid ??= DB::table('network_router_wilayah')->where('network_router_id', $map->router_id)->value('wilayah_id');
        $wid ??= DB::table('wilayah')->orderBy('id')->value('id');

        $monthly = (int) InternetPackage::findOrFail($map->package_id)->monthly_price;
        $today = InvoicePeriod::today();
        $prevStart = $today->copy()->startOfMonth()->subMonthNoOverflow();
        $periodDays = $prevStart->daysInMonth;
        $startedAt = $prevStart->copy()->addDays(10)->format('Y-m-d');
        $expPro = intdiv($monthly * min(30, $periodDays - 10) + 15, 30);
        $y = $today->year;
        $m = $today->month;
        $this->mark('INFO', "Skenario: periode tagihan bulan $y-$m, mulai pelanggan uji $startedAt, harga bulanan $monthly, prorata diharapkan $expPro, wilayah=$wid");

        $provision = app(CustomerProvisioning::class);
        $gen = app(InvoiceGenerator::class);
        $make = function (string $label, string $mode, string $started) use ($provision, $map, $wid) {
            $c = $provision->register([
                'name' => 'AUDIT '.$label, 'whatsapp' => null, 'address' => null,
                'network_router_id' => $map->router_id, 'wilayah_id' => $wid, 'internet_package_id' => $map->package_id,
                'pppoe_username' => 'audit-'.Str::lower(Str::random(8)), 'pppoe_password' => 'audit-pass-'.Str::random(6),
                'started_at' => $started, 'invoice_day' => 1, 'price_mode' => 'package', 'tax_enabled' => false,
                'notes' => null, 'first_billing_mode' => $mode,
            ]);
            DB::table('customers')->where('id', $c->id)->update(['service_status' => 'active']);

            return $c->id;
        };
        $issue = function (int $id, bool $auto) use ($gen, $y, $m) {
            $res = $gen->issue($id, $y, $m, null, 'audit', $auto);
            if ($res instanceof Invoice) {
                return [$res, null];
            }

            return array_pad((array) $res, 2, null);
        };

        $pro = $make('PRORATA', 'prorata', $startedAt);
        $full = $make('FULL', 'full', $startedAt);
        $skip = $make('SKIP', 'skip', $startedAt);
        $legacy = $make('LEGACY', 'prorata', $startedAt);
        DB::table('customers')->where('id', $legacy)->update(['first_billing_mode' => null]);
        $future = $make('FUTURE', 'prorata', $today->copy()->addDays(5)->format('Y-m-d'));
        $this->mark('PASS', 'Registrasi 5 pelanggan uji berhasil (tanpa menyentuh router)');

        [$inv, $why] = $issue($pro, false);
        $this->expectPrice('Prorata', $inv, $why, $expPro);
        [$inv2, $why2] = $issue($pro, false);
        $this->mark($inv2 === null ? 'PASS' : 'FAIL', 'Penerbitan ganda pada periode yang sama ditolak: '.($why2 ?? '(tanpa alasan)'));

        try {
            $ledger = app(BillingLedger::class);
            $ledger->receive($full, $super, [
                'request_key' => (string) Str::uuid(), 'amount' => 50000, 'paid_on' => $today->format('Y-m-d'),
                'method' => 'cash', 'reference' => 'AUDIT', 'confirmed' => true,
            ], 'deposit');
            $this->mark('INFO', 'Saldo setelah setoran 50000: '.json_encode($ledger->balance($full)));
        } catch (\Throwable $e) {
            $this->mark('WARN', 'Setoran saldo gagal (kemungkinan kunci data berbeda): '.$this->safe($e));
        }
        [$invF, $whyF] = $issue($full, true);
        $this->expectPrice('Full (otomatis)', $invF, $whyF, $monthly);
        if ($invF) {
            $this->mark('INFO', "Tagihan Full: total={$invF->total} terbayar={$invF->paid_amount} status={$invF->status}");
            $this->mark(((int) $invF->paid_amount <= (int) $invF->total) ? 'PASS' : 'FAIL', 'Terbayar tidak melebihi total');
        }
        try {
            $this->mark('INFO', 'Saldo Full setelah tagihan: '.json_encode(app(BillingLedger::class)->balance($full)));
        } catch (\Throwable $e) {
        }

        [$invS, $whyS] = $issue($skip, false);
        $this->mark('INFO', 'Mode skip (manual): '.($invS ? "terbit total={$invS->total}" : 'tidak terbit - '.$whyS));
        [$invL, $whyL] = $issue($legacy, true);
        $this->mark($invL === null ? 'PASS' : 'WARN', 'Pelanggan lama (mode kosong) tidak terbit otomatis: '.($whyL ?? 'terbit'));
        [$invL2, $whyL2] = $issue($legacy, false);
        $this->expectPrice('Pelanggan lama (manual) harga penuh', $invL2, $whyL2, $monthly);
        [$invFu, $whyFu] = $issue($future, false);
        $this->mark($invFu === null ? 'PASS' : 'FAIL', 'Pelanggan mulai di masa depan tidak ditagih: '.($whyFu ?? 'terbit'));

        $saldoId = $make('SALDO', 'skip', $startedAt);
        try {
            app(BillingLedger::class)->receive($saldoId, $super, ['request_key' => (string) Str::uuid(), 'amount' => 30000, 'paid_on' => $today->format('Y-m-d'), 'method' => 'cash', 'reference' => 'AUDIT', 'confirmed' => true], 'deposit');
        } catch (\Throwable $e) {
            $this->mark('WARN', 'Setoran uji saldo gagal: '.$this->safe($e));
        }
        // Penghapusan
        $svc = app(CustomerRemoval::class);
        $this->mark('INFO', 'Method CustomerRemoval: '.implode(', ', array_map(fn ($r) => $r->getName(), (new \ReflectionClass($svc))->getMethods(\ReflectionMethod::IS_PUBLIC))));
        if (method_exists($svc, 'billingOnly')) {
            try {
                $svc->billingOnly($saldoId, $super);
                $this->mark('FAIL', 'Pelanggan bersaldo seharusnya ditolak saat hapus billing saja, tetapi lolos');
            } catch (\Throwable $e) {
                $this->mark($e instanceof \TypeError ? 'WARN' : 'PASS', 'Hapus pelanggan bersaldo ditolak: '.Str::limit($e->getMessage(), 120));
            }
            try {
                $svc->billingOnly($pro, $super);
                $row = DB::table('customers')->where('id', $pro)->first();
                $this->mark($row->deleted_at ? 'PASS' : 'FAIL', 'Pelanggan Prorata diarsipkan (deleted_at terisi)');
                $this->mark($row->pppoe_username === null ? 'PASS' : 'FAIL', 'Username dilepas setelah arsip');
                $this->mark(DB::table((new Invoice)->getTable())->where('customer_id', $pro)->exists() ? 'PASS' : 'FAIL', 'Tagihan pelanggan terarsip tetap ada');
                if ($admin && in_array((int) $wid, array_map('intval', $admin->accessibleWilayahIds()), true)) {
                    config(['wilayah.enforce' => true]);
                    Auth::guard()->setUser($admin);
                    $visible = Invoice::where('customer_id', $pro)->exists();
                    $this->mark($visible ? 'PASS' : 'FAIL', "Admin #{$admin->id} tetap melihat tagihan pelanggan terarsip di wilayahnya");
                    Auth::guard()->forgetUser();
                } else {
                    $this->mark('SKIP', 'Tidak ada admin wilayah yang cocok untuk uji visibilitas arsip');
                }
                $reuse = $make('REUSE', 'full', $startedAt);
                $this->mark('PASS', 'Pelanggan baru dapat dibuat lagi setelah arsip (id '.$reuse.')');
            } catch (\Throwable $e) {
                $this->mark('WARN', 'Uji hapus billing saja tidak tuntas: '.$this->safe($e));
            }
        } else {
            $this->mark('SKIP', 'Method billingOnly tidak ditemukan; lihat daftar method di atas');
        }
        $this->mark('SKIP', 'Pembayaran tagihan, koreksi, dan pembatalan kuitansi belum diuji (API BillingLedger belum dikenali); kirim BillingLedger.php agar ditambahkan');
    }

    private function expectPrice(string $label, $inv, $why, int $expected): void
    {
        if (! $inv) {
            $this->mark('WARN', "$label: tidak terbit - ".($why ?? 'tanpa alasan'));

            return;
        }
        $total = (int) $inv->total;
        $this->mark($total === $expected ? 'PASS' : 'FAIL', "$label: total=$total diharapkan=$expected status={$inv->status}");
    }
}
