<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1728">
    <title>Dashboard | Tagihan dan Management Jaringan</title>

    @foreach([
        'billing-dashboard',
        'billing-theme',
        'billing-page-header',
        'billing-launcher',
        'billing-network-header',
        'billing-router-status',
        'billing-dashboard-v2',
        'billing-dashboard-finance',
        'billing-dashboard-merged',
    ] as $file)
        @if(is_file(public_path('css/'.$file.'.css')))
            <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
        @endif
    @endforeach

    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body class="billing-launcher-layout dashboard-v2">
@include('partials.billing-page-header', [
    'headerTitle' => 'Kelola Tagihan Dengan mudah',
])

@php
    $money = fn ($value) => ($value < 0 ? '-Rp' : 'Rp')
        .number_format(abs((int) $value), 0, ',', '.');
@endphp

<main class="dd-page">

    @if($canBilling)
        <article class="dd-card dd-finance-result dd-saldo-top
            {{ $summary['finance']['cash_difference'] < 0
                ? 'dd-finance-negative'
                : 'dd-finance-positive' }}">

            <div class="dd-saldo-heading">
                <h2 class="dd-saldo-title">
                    {{ auth()->user()?->name }}
                </h2>

                <form id="dd-saldo-month-form"
                    class="dd-saldo-month-form"
                    method="GET" action="{{ route('dashboard') }}">
                    <label class="dd-saldo-calendar dd-saldo-month-picker"
                        for="dd-saldo-month-direct"
                        title="Pilih bulan dan tahun">
                        @php
                            $dashboardCalendarToday = \App\Services\InvoicePeriod::today();
                            $dashboardCalendarMonths = [
                                1 => 'JAN', 2 => 'FEB', 3 => 'MAR',
                                4 => 'APR', 5 => 'MEI', 6 => 'JUN',
                                7 => 'JUL', 8 => 'AGU', 9 => 'SEP',
                                10 => 'OKT', 11 => 'NOV', 12 => 'DES',
                            ];
                        @endphp
                        <span class="dd-calendar-live" aria-hidden="true">
                            <span class="dd-calendar-live-month">
                                {{ $dashboardCalendarMonths[$dashboardCalendarToday->month] }}
                            </span>
                            <span class="dd-calendar-live-day">
                                {{ $dashboardCalendarToday->day }}
                            </span>
                            <span class="dd-calendar-live-year">
                                {{ $dashboardCalendarToday->year }}
                            </span>
                        </span>
                        <input id="dd-saldo-month-direct"
                            type="month" name="month"
                            value="{{ $month }}" min="2000-01" required
                            aria-label="Pilih bulan dan tahun, periode diterapkan otomatis">
                    </label>
                </form>
            </div>

            <strong class="dd-finance-result-value">
                {{ $money($summary['finance']['cash_difference']) }}
            </strong>
            <div class="dd-saldo-details">
                <p class="dd-saldo-period-label">
                    {{ $month === \App\Services\InvoicePeriod::today()->format('Y-m')
                        ? 'Saldo Bulan ini'
                        : 'Saldo Periode' }}
                </p>
                <p class="dd-saldo-caption">hasil setelah dikurangi pengeluaran</p>
            </div>

            <div class="dd-card-business-brand">
                <span class="dd-card-brand-circles" aria-hidden="true">
                    <span class="dd-card-brand-circle-red"></span>
                    <span class="dd-card-brand-circle-orange"></span>
                </span>
                <span class="dd-card-business-name">
                    {{ \App\Models\BillingSetting::current()->business_name }}
                </span>
            </div>
        </article>

        <script>
        (() => {
            const form = document.getElementById('dd-saldo-month-form');
            const input = document.getElementById('dd-saldo-month-direct');

            if (!form || !input) return;

            const initialMonth = input.value;
            let submitting = false;

            input.addEventListener('click', () => {
                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                    } catch (_) {
                        // Jika tidak didukung, gunakan perilaku input bawaan.
                    }
                }
            });

            input.addEventListener('change', () => {
                if (
                    submitting
                    || !input.value
                    || input.value === initialMonth
                    || !input.checkValidity()
                ) {
                    return;
                }

                submitting = true;
                form.requestSubmit();
            });
        })();
        </script>
    @endif

    @if($canBilling || $canManage)
        <div class="nm-card {{ $canBilling && $canManage ? 'nm-both' : '' }}">
            @if($canBilling)
                @include('dashboard._customer-connections')
            @endif

            @if($canManage)
                <div class="dd-monitor-top">
                    @include('dashboard._router-monitor')
                </div>
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="dd-warning" role="alert">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif



    @if($canBilling)
        @php
            $f = $summary['finance'];
            $c = $summary['customers'];
        @endphp

        <section aria-labelledby="dd-finance-title">
            <div class="dd-section-head dd-finance-heading">
                <h2 id="dd-finance-title">Ringkasan keuangan</h2>

                
            </div>

            <div class="dd-finance-layout">
                

                <div class="dd-finance-metrics">
                    <article class="dd-card dd-finance-metric dd-finance-income">
                        <span class="dd-eyebrow">Total Pemasukan</span>
                        <strong class="dd-finance-value">
                            {{ $money($f['cash_in']) }}
                        </strong>
                        <p class="dd-note">
                            {{ $f['receipt_count'] }} penerimaan aktif
                            · {{ $monthLabel }}
                        </p>
                    </article>

                    <article class="dd-card dd-finance-metric dd-finance-expense">
                        <span class="dd-eyebrow">Pengeluaran</span>
                        <strong class="dd-finance-value">
                            {{ $money($f['expenses']) }}
                        </strong>
                        <p class="dd-note">
                            {{ $f['expense_count'] }} pengeluaran aktif
                            · {{ $monthLabel }}
                        </p>
                    </article>

                    <article class="dd-card dd-finance-metric dd-finance-unpaid">
                        <span class="dd-eyebrow">Tagihan Belum Bayar</span>
                        <strong class="dd-finance-value">
                            {{ $money($f['period_receivables']) }}
                        </strong>
                        <p class="dd-note">
                            {{ $f['period_open_count'] }} invoice terbit
                            pada {{ $monthLabel }} · sisa saat ini
                        </p>
                    </article>
                </div>

            </div>
        </section>

        <section class="dd-card dd-panel dd-transactions" aria-labelledby="dd-transactions-title">
            <div class="dd-section-head">
                <h2 id="dd-transactions-title">Transaksi terbaru pada periode</h2>
                <span>{{ $monthLabel }} · menurut tanggal transaksi</span>
            </div>

            @forelse($summary['transactions'] as $transaction)
                <div class="dd-row">
                    <div>
                        <p>{{ $transaction['description'] }}</p>
                        <small>
                            {{ \Carbon\CarbonImmutable::parse($transaction['date'])->format('d/m/Y') }}
                            · {{ $transaction['label'] }} · {{ $transaction['method'] }}
                        </small>
                    </div>
                    <div class="dd-row-end">
                        <strong class="{{ $transaction['kind'] === 'in' ? 'dd-in' : 'dd-out' }}">
                            {{ $transaction['kind'] === 'in' ? '+' : '−' }}{{ $money($transaction['amount']) }}
                        </strong>
                        <small>{{ $transaction['kind'] === 'in' ? 'Kas masuk' : 'Pengeluaran' }}</small>
                    </div>
                </div>
            @empty
                <div class="dd-empty">Belum ada penerimaan atau pengeluaran aktif pada bulan ini.</div>
            @endforelse
        </section>
    @else
        <section class="dd-card dd-panel" style="margin-top:18px">
            <div class="dd-section-head"><h2>Ruang kerja</h2></div>
            <p class="dd-note">Ringkasan pelanggan dan keuangan hanya tersedia untuk akun dengan hak akses billing.</p>
        </section>
    @endif

    <footer class="dd-footer">
        <span>© {{ date('Y') }} Tagihan dan Management Jaringan</span>
        <span>Periode tanggal keuangan mengikuti zona tagihan WIB.</span>
    </footer>
</main>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
@if($canManage)
    <script src="{{ asset('js/billing-router-monitor.js') }}?v={{ filemtime(public_path('js/billing-router-monitor.js')) }}" defer></script>
@endif
</body>
</html>
