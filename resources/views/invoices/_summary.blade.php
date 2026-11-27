@php($fmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'))
<div class="iv-panel">
    <h2 class="iv-customer">{{ $quote['customer_name'] }}</h2>
    <p class="iv-muted">{{ $quote['customer_code'] }} · {{ $quote['package_name'] }}</p>

    <dl class="iv-summary">
        <div><dt>Router</dt><dd>{{ $quote['router_name'] }}</dd></div>
        <div><dt>Sistem</dt><dd>Pascabayar</dd></div>
        <div><dt>Tanggal terbit</dt><dd>{{ $fmt($quote['issued_on']) }}</dd></div>
        <div><dt>Jatuh tempo</dt><dd>{{ $fmt($quote['due_on']) }}</dd></div>
        <div class="iv-wide">
            <dt>Periode pemakaian</dt>
            <dd>{{ $fmt($quote['period_start']) }} sampai {{ $fmt($quote['period_end']) }}</dd>
        </div>
        <div><dt>Harga bulanan</dt><dd>Rp{{ number_format($quote['monthly_price'], 0, ',', '.') }}</dd></div>
        @if(($quote['billing_prorata']['is_prorated'] ?? false))
            <div class="iv-wide">
                <dt>Perhitungan prorata</dt>
                <dd>
                    {{ $quote['billing_prorata']['active_days'] }} hari layanan
                    / dasar {{ $quote['billing_prorata']['prorata_basis_days'] }} hari
                    · {{ $quote['billing_prorata']['paused_days'] - ($quote['billing_prorata']['pre_start_days'] ?? 0) }} hari jeda @if(($quote['billing_prorata']['pre_start_days'] ?? 0) > 0) · {{ $quote['billing_prorata']['pre_start_days'] }} hari sebelum mulai @endif
                </dd>
            </div>
            <div>
                <dt>Biaya layanan setelah prorata</dt>
                <dd>Rp{{ number_format($quote['price'], 0, ',', '.') }}</dd>
            </div>
        @endif
        <div><dt>Sumber harga</dt><dd>{{ $quote['price_mode'] === 'custom' ? 'Harga khusus' : 'Ikuti paket' }}</dd></div>
        <div><dt>Sebelum pajak</dt><dd>Rp{{ number_format($quote['net'], 0, ',', '.') }}</dd></div>
        <div>
            <dt>{{ $quote['tax_name'] }}</dt>
            <dd>
                Rp{{ number_format($quote['tax'], 0, ',', '.') }}
                @if($quote['tax_enabled'])
                    ({{ rtrim(rtrim(number_format($quote['tax_rate_bps'] / 100, 2, '.', ''), '0'), '.') }}%, {{ $quote['tax_mode'] === 'include' ? 'Include' : 'Exclude' }})
                @else
                    (tidak dikenakan)
                @endif
            </dd>
        </div>
    </dl>
    <div class="iv-total"><span>Total invoice</span><strong>Rp{{ number_format($quote['total'], 0, ',', '.') }}</strong></div>
    @if($invoice->status !== 'void')
        <div class="iv-payment-recap">
            <div>
                <span>Pembayaran aktif</span>
                <strong>Rp{{ number_format($invoice->paid_amount, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Sisa tagihan</span>
                <strong>Rp{{ number_format($invoice->remaining, 0, ',', '.') }}</strong>
            </div>
        </div>
    @else
        <p class="iv-muted">Invoice ini dibatalkan dan tidak ditagihkan.</p>
    @endif
</div>
