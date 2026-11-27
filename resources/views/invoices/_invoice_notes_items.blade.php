<p class="iv-muted">{{ $invoice->customer_name }} · {{ $invoice->number }}</p>

@if($receipts->total() > 0)
    <p class="iv-muted">Pilih nota penerimaan yang membayar invoice ini.</p>

    @foreach($receipts as $receipt)
        <article class="iv-notes-item">
            <div class="iv-notes-top">
                <strong>{{ $receipt->paid_on?->format('d/m/Y') ?? '-' }}</strong>
                <span>Penerimaan #{{ $receipt->id }}</span>
            </div>
            <p class="iv-muted">Penerima: {{ $receipt->recorded_by_name ?? '-' }}</p>
            <p class="iv-muted">
                Alokasi ke invoice ini:
                Rp{{ number_format($receipt->allocations->sum('amount'), 0, ',', '.') }}
            </p>
            <button type="button" class="cu-button cu-primary"
                data-iv-nota="{{ $receipt->id }}">Lihat nota</button>
        </article>
    @endforeach

    <p class="iv-muted">
        Nota menampilkan penerimaan aslinya, yang dapat mencakup lebih dari satu invoice.
    </p>
    @php($pages = $receipts)
@else
    <p class="iv-muted">
        Tidak ada nota penerimaan aktif yang dialokasikan langsung ke invoice ini.
    </p>

    @if($uses && $uses->total() > 0)
        <p class="iv-muted">
            Invoice ini memiliki pembayaran dari titip saldo.
            Pemakaian saldo bukan penerimaan uang baru.
        </p>
        @foreach($uses as $use)
            <article class="iv-notes-item">
                <div class="iv-notes-top">
                    <strong>{{ $use->created_at?->setTimezone('Asia/Jakarta')->format('d/m/Y') ?? '-' }}</strong>
                    <span>Pemakaian titip saldo</span>
                </div>
                <p class="iv-muted">Petugas: {{ $use->created_by_name ?? '-' }}</p>
                <p>Rp{{ number_format(abs($use->amount), 0, ',', '.') }}</p>
            </article>
        @endforeach
    @endif

    <p class="iv-muted">
        Nota penerimaan yang dibatalkan dapat dilihat melalui Riwayat pembayaran.
    </p>
    @php($pages = $uses)
@endif

@if($pages && $pages->lastPage() > 1)
    <div class="iv-notes-pagination">
        <button type="button" class="cu-button"
            data-iv-notes-page="{{ $pages->currentPage() - 1 }}"
            @disabled(!$pages->previousPageUrl())>Sebelumnya</button>
        <span class="iv-muted">
            {{ $pages->currentPage() }} / {{ $pages->lastPage() }}
        </span>
        <button type="button" class="cu-button"
            data-iv-notes-page="{{ $pages->currentPage() + 1 }}"
            @disabled(!$pages->hasMorePages())>Berikutnya</button>
    </div>
@endif
