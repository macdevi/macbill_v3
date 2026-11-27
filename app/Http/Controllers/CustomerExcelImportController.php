<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerExcelImporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerExcelImportController extends Controller
{
    public function index()
    {
        return $this->page(['batch' => null]);
    }

    public function preview(Request $request, CustomerExcelImporter $importer)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
            'started_at' => ['required', 'date_format:Y-m-d'],
            'wilayah_id' => ['nullable', 'integer', 'exists:wilayah,id'],
        ]);

        $allowedArea = \App\Models\Scopes\WilayahScope::allowedIds();
        if (!empty($data['wilayah_id']) && $allowedArea !== null
            && !in_array((int) $data['wilayah_id'], $allowedArea, true)) {
            return back()->withErrors(['wilayah_id' => 'Anda tidak memiliki akses ke wilayah ini.']);
        }

        try {
            $rows = $importer->read($request->file('file')->getRealPath());
            if ($rows === []) {
                return back()->withErrors(['file' => 'Tidak ada baris pelanggan pada file.']);
            }

            $review = $importer->review($rows, $data['started_at']);
            $signatures = array_column($review, 'signature');

            $payload = Crypt::encryptString(json_encode([
                'rows' => $rows,
                'started_at' => $data['started_at'],
                'wilayah_id' => !empty($data['wilayah_id']) ? (int) $data['wilayah_id'] : null,
                'signatures' => $signatures,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $ignored) {
            return back()->withErrors([
                'file' => 'File belum dapat dibaca. Gunakan template terbaru 13 kolom, lembar Pelanggan, maksimal 1.000 baris dan 5 MB. File dengan struktur berlebihan, macro, atau tautan workbook eksternal tidak didukung.',
            ]);
        }

        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $id, $payload) {
            DB::table('customer_excel_previews')->where('expires_at', '<=', now())->delete();
            DB::table('customer_excel_previews')->insert([
                'id' => $id,
                'user_id' => $request->user()->id,
                'encrypted_payload' => $payload,
                'expires_at' => now()->addMinutes(20),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('customer-excel-import.show', $id);
    }

    public function show(Request $request, string $batch, CustomerExcelImporter $importer)
    {
        $snapshot = DB::table('customer_excel_previews')
            ->where('id', $batch)->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())->first();

        if (!$snapshot) {
            return redirect()->route('customer-excel-import.index')
                ->withErrors(['preview' => 'Preview tidak tersedia atau kedaluwarsa. Unggah ulang file.']);
        }

        try {
            $payload = json_decode(
                Crypt::decryptString($snapshot->encrypted_payload),
                true, 512, JSON_THROW_ON_ERROR
            );
            $review = $importer->review($payload['rows'], $payload['started_at']);
        } catch (\Throwable $ignored) {
            return redirect()->route('customer-excel-import.index')
                ->withErrors(['preview' => 'Preview tidak dapat dibuka. Unggah ulang file.']);
        }

        $display = [];
        $summary = ['ready' => 0, 'duplicate' => 0, 'invalid' => 0];

        foreach ($review as $i => $row) {
            if (
                $row['ready'] &&
                !hash_equals($payload['signatures'][$i], $row['signature'])
            ) {
                $row['ready'] = false;
                $row['errors'][] = 'Pengaturan/router/paket berubah sejak unggah. Buat preview baru.';
            }

            $category = $row['duplicate'] ? 'duplicate' : ($row['ready'] ? 'ready' : 'invalid');
            $summary[$category]++;

            $record = $row['data'];
            $display[] = [
                'index' => $i,
                'row_number' => $row['row_number'],
                'name' => $record['name'],
                'router' => $row['router_name'],
                'package' => $row['package_name'],
                'identity' => $record['service_type'] === 'pppoe'
                    ? $record['pppoe_username'] : $record['static_ip'],
                'password_available' => $record['pppoe_password'] !== null,
                'status' => $record['service_status'],
                'custom' => $record['price_mode'] === 'custom',
                'total' => $row['quote']['total'] ?? null,
                'day' => $row['effective_day'],
                'tax' => $row['tax_percent'],
                'errors' => $row['errors'],
                'duplicate' => $row['duplicate'],
                'ready' => $row['ready'],
            ];
        }

        $page = max(1, (int) $request->query('page', 1));
        $rows = new LengthAwarePaginator(
            array_slice($display, ($page - 1) * 50, 50),
            count($display), 50, $page,
            ['path' => route('customer-excel-import.show', $batch)]
        );

        return $this->page([
            'batch' => $snapshot, 'rows' => $rows, 'summary' => $summary,
            'startedAt' => $payload['started_at'],
        ]);
    }

    public function commit(Request $request, string $batch, CustomerExcelImporter $importer)
    {
        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1', 'max:50'],
            'selected.*' => ['required', 'integer', 'min:0', 'max:999', 'distinct'],
        ], [
            'selected.required' => 'Pilih minimal satu baris siap import.',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $batch, $data, $importer) {
                $snapshot = DB::table('customer_excel_previews')
                    ->where('id', $batch)->where('user_id', $request->user()->id)
                    ->where('expires_at', '>', now())->lockForUpdate()->first();

                if (!$snapshot) {
                    throw new \RuntimeException('Preview unavailable');
                }

                $payload = json_decode(
                    Crypt::decryptString($snapshot->encrypted_payload),
                    true, 512, JSON_THROW_ON_ERROR
                );
                $review = $importer->review($payload['rows'], $payload['started_at']);
                $created = 0;
                $skipped = 0;

                foreach ($data['selected'] as $index) {
                    $index = (int) $index;
                    if (!isset($review[$index])) {
                        throw new \RuntimeException('Invalid row');
                    }

                    $row = $review[$index];
                    if ($row['duplicate']) {
                        $skipped++;
                        continue;
                    }

                    if (
                        !$row['ready'] ||
                        !hash_equals($payload['signatures'][$index], $row['signature'])
                    ) {
                        throw new \RuntimeException('Review changed');
                    }

                    $customer = new Customer($row['data']);
                    $customer->wilayah_id = \App\Services\ImportWilayah::resolve(!empty($payload['wilayah_id']) ? (int) $payload['wilayah_id'] : null, (int) $customer->network_router_id);
                    $customer->save();
                    $created++;
                }
                return compact('created', 'skipped');
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        } catch (\Throwable $ignored) {
            return back()->withErrors([
                'import' => 'Import dibatalkan tanpa menyimpan sebagian baris. Preview mungkin kedaluwarsa, data berubah, atau terjadi bentrok. Tinjau ulang atau unggah ulang file.',
            ]);
        }

        return redirect()->route('customer-excel-import.show', [
            'batch' => $batch,
            'page' => max(1, (int) $request->query('page', 1)),
        ])->with(
            'status',
            $result['created'].' pelanggan ditambahkan, '.$result['skipped'].
            ' duplikat dilewati. Tidak ada perubahan MikroTik atau penerbitan invoice.'
        );
    }

    public function discard(Request $request, string $batch)
    {
        DB::table('customer_excel_previews')->where('id', $batch)
            ->where('user_id', $request->user()->id)->delete();

        return redirect()->route('customers.index')
            ->with('status', 'Preview Excel ditutup dan data sementara dihapus.');
    }

    private function page(array $data)
    {
        $allowedArea = \App\Models\Scopes\WilayahScope::allowedIds();
        $choices = \App\Models\Wilayah::query()
            ->when($allowedArea !== null, fn ($q) => $q->whereIn('id', $allowedArea))
            ->orderBy('name')->get(['id', 'name']);

        return response()->view('customers.excel-import', $data + ['wilayahChoices' => $choices])
            ->header('Cache-Control', 'private, no-store');
    }
}
