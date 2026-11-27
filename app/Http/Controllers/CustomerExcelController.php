<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Services\CustomerExcelWorkbook;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CustomerExcelController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'router' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,stopped'],
            'type' => ['nullable', 'in:pppoe,static'],
        ]);
    }

    private function query(array $filters)
    {
        $query = Customer::query();

        if (filled($filters['q'] ?? null)) {
            $q = trim($filters['q']);
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('whatsapp', 'like', '%'.$q.'%')
                    ->orWhere('pppoe_username', 'like', '%'.$q.'%')
                    ->orWhere('static_ip', 'like', '%'.$q.'%');

                if (preg_match('/^(?:PLG-)?0*(\d+)$/i', $q, $match)) {
                    $builder->orWhere('id', (int) $match[1]);
                }
            });
        }

        if (filled($filters['router'] ?? null)) {
            $query->where('network_router_id', $filters['router']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('service_status', $filters['status']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('service_type', $filters['type']);
        }

        return $query;
    }

    public function template(CustomerExcelWorkbook $workbook)
    {
        return $this->download(
            $workbook->template(),
            'template-pelanggan-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function confirm(Request $request)
    {
        $filters = $this->filters($request);

        return response()->view('customers.excel-export', [
            'filters' => $filters,
            'count' => $this->query($filters)->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, CustomerExcelWorkbook $workbook)
    {
        $request->validate(['confirm_password_export' => ['accepted']]);
        $filters = $this->filters($request);
        $query = $this->query($filters);

        if ((clone $query)->count() > 5000) {
            return redirect()->route('customer-excel.confirm', $filters)
                ->withErrors(['export' => 'Batasi export maksimal 5.000 pelanggan dengan filter.']);
        }

        try {
            $book = $workbook->export(
                $query->with(['router', 'package'])->orderBy('id')->get(),
                BillingSetting::current()
            );
        } catch (\Throwable $ignored) {
            return redirect()->route('customer-excel.confirm', $filters)
                ->withErrors(['export' => 'Export belum dapat disiapkan. Detail sensitif tidak ditampilkan.']);
        }

        return $this->download(
            $book,
            'pelanggan-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function download(Spreadsheet $book, string $name)
    {
        $directory = storage_path('app/private/customer-excel');
        $path = null;

        try {
            if (
                !is_dir($directory) &&
                !mkdir($directory, 0700, true) &&
                !is_dir($directory)
            ) {
                throw new \RuntimeException('Temporary directory unavailable');
            }

            foreach (glob($directory.'/billing_xlsx_*') ?: [] as $old) {
                if (is_file($old) && filemtime($old) < time() - 3600) {
                    @unlink($old);
                }
            }

            $path = tempnam($directory, 'billing_xlsx_');
            if ($path === false) {
                throw new \RuntimeException('Temporary file unavailable');
            }
            if (!chmod($path, 0600)) {
                throw new \RuntimeException('Temporary file permissions failed');
            }

            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);

            return response()->download($path, $name, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $ignored) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
            abort(500, 'File Excel belum dapat dibuat. Detail sensitif tidak ditampilkan.');
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
