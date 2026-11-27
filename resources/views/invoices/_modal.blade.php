@php($quote = $invoice->snapshot)
<dialog id="iv-dialog-{{ $invoice->id }}" class="iv-dialog iv-detail-dialog"
    aria-labelledby="iv-dialog-title-{{ $invoice->id }}">
    <div class="iv-dialog-head">
        <div>
            <h2 id="iv-dialog-title-{{ $invoice->id }}">{{ $invoice->number }}</h2>
            <span class="iv-badge iv-detail-status iv-detail-status-{{ $invoice->status }}">{{ $invoice->status_label }}</span>
        </div>
        <button type="button" class="iv-dialog-x" data-iv-close aria-label="Tutup">&times;</button>
    </div>

    <div class="iv-dialog-body">
        @include('invoices._summary')

        <p class="iv-muted">
            Diterbitkan oleh {{ $quote['created_by_name'] ?? 'Sistem' }}
            ({{ ($quote['source'] ?? 'manual') === 'auto' ? 'otomatis' : 'manual' }}).
        </p>

        <div class="iv-history-entry">
            <button type="button" class="cu-button"
                data-iv-history="{{ route('invoices.payment-history', $invoice) }}">
                Lihat riwayat pembayaran
            </button>
        </div>

        @if($invoice->status === 'void')
            <div class="iv-panel">
                <h3 class="iv-customer">Invoice dibatalkan</h3>
                <p class="iv-muted">Oleh {{ $invoice->voided_by_name }} · {{ $invoice->voided_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                <p class="iv-reason">{{ $invoice->void_reason }}</p>
            </div>
        @elseif($invoice->status === 'unpaid')
            <details class="iv-panel">
                <summary class="iv-cancel-heading">Batalkan invoice</summary>
                <form class="iv-form" method="POST" action="{{ route('invoices.void', $invoice) }}">
                    @csrf
                    <label for="iv-reason-{{ $invoice->id }}">Alasan pembatalan</label>
                    <textarea id="iv-reason-{{ $invoice->id }}" name="reason" rows="3" minlength="5" maxlength="1000" required></textarea>
                    <label class="iv-confirm">
                        <input type="checkbox" name="confirmed" value="1" required>
                        <span>Saya memastikan invoice ini perlu dibatalkan. Nomor dan riwayatnya tetap disimpan.</span>
                    </label>
                    <button class="cu-button" type="submit">Konfirmasi pembatalan</button>
                </form>
            </details>
        @endif
    </div>

    <div class="iv-dialog-foot">
        @include('invoices._actions')
        <button type="button" class="cu-button" data-iv-close>Tutup</button>
    </div>
</dialog>
