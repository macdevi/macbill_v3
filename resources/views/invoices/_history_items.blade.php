@forelse($rows as $row)
    @php
        $isReceipt = $row->kind === 'receipt';
        $entry = $isReceipt
            ? $receiptMap->get($row->id)
            : $useMap->get($row->id);
    @endphp

    @if($entry)
        @php($isVoid = $entry->status === 'void')
        <article class="iv-hist-item">
            <div class="iv-hist-top">
                <strong>
                    @if($isReceipt)
                        {{ $entry->paid_on?->format('d/m/Y') ?? '-' }}
                    @else
                        {{ $entry->created_at?->setTimezone('Asia/Jakarta')->format('d/m/Y') ?? '-' }}
                    @endif
                </strong>
                <span class="iv-badge {{ $isVoid ? 'iv-hist-void' : 'iv-hist-active' }}">
                    {{ $isVoid ? 'Dibatalkan' : 'Aktif' }}
                </span>
            </div>

            <p class="iv-muted">
                {{ $isReceipt ? 'Penerima' : 'Petugas' }}:
                {{ $isReceipt ? ($entry->recorded_by_name ?? '-') : ($entry->created_by_name ?? '-') }}
            </p>

            <p class="iv-hist-amount">
                Rp{{ number_format(abs($entry->amount), 0, ',', '.') }}
                @if(!$isReceipt)
                    <span class="iv-muted"> · Pemakaian titip saldo</span>
                @endif
            </p>

            @if($isReceipt)
                <p class="iv-muted">Invoice yang tercatat pada penerimaan ini:</p>
                <ul class="iv-hist-invoices">
                    @forelse($entry->allocations as $allocation)
                        <li>
                            {{ $allocation->invoice?->number ?? 'Invoice tidak tersedia' }}
                            · Rp{{ number_format($allocation->amount, 0, ',', '.') }}
                            @if($allocation->status === 'void')
                                <span class="iv-muted">(dibatalkan)</span>
                            @endif
                        </li>
                    @empty
                        <li>Titip saldo tanpa alokasi langsung ke invoice.</li>
                    @endforelse
                </ul>

                <button type="button" class="cu-button"
                    data-iv-nota="{{ $entry->id }}">Lihat nota</button>
            @else
                <p class="iv-muted">Tagihan: {{ $entry->invoice?->number ?? '-' }}</p>
            @endif

            @if($isVoid)
                <details class="iv-hist-correction">
                    <summary>Detail pembatalan</summary>
                    <p class="iv-muted">
                        Oleh {{ $entry->voided_by_name ?? '-' }}
                        · {{ $entry->voided_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} WIB
                    </p>
                    <p class="iv-reason">{{ $entry->void_reason ?? '-' }}</p>
                </details>
            @elseif($entry->status === 'active')
                <div class="iv-hist-correction">
                    @if($isReceipt)
                        @include('payments._void', [
                            'action' => route('payments.void', $entry),
                            'id' => 'hist-r'.$entry->id,
                            'label' => 'Batalkan penerimaan (seluruh invoice terkait)',
                        ])
                    @else
                        @include('payments._void', [
                            'action' => route('balances.void-use', $entry),
                            'id' => 'hist-u'.$entry->id,
                            'label' => 'Batalkan pemakaian saldo',
                        ])
                    @endif
                </div>
            @endif
        </article>
    @endif
@empty
    <p class="iv-muted">Belum ada riwayat yang sesuai dengan filter ini.</p>
@endforelse

<div class="iv-hist-pagination">
    <button type="button" class="cu-button"
        data-iv-history-page="{{ $rows->currentPage() - 1 }}"
        @disabled(!$rows->previousPageUrl())>Sebelumnya</button>
    <span class="iv-muted">
        {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
    </span>
    <button type="button" class="cu-button"
        data-iv-history-page="{{ $rows->currentPage() + 1 }}"
        @disabled(!$rows->hasMorePages())>Berikutnya</button>
</div>
<p class="iv-muted iv-hist-count">{{ $rows->total() }} transaksi sesuai filter.</p>
