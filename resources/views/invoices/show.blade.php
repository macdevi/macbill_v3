@extends('invoices.layout')
@section('title', 'Detail Invoice')
@section('content')
<div class="iv-heading">
    <div><h1>{{ $invoice->number }}</h1><p>{{ $invoice->status_label }}</p></div>
    <a class="cu-button" href="{{ route('invoices.index') }}">Daftar Tagihan</a>
</div>

@include('invoices._summary')

<div class="iv-panel">
    <p class="iv-muted">
        Diterbitkan oleh {{ $quote['created_by_name'] ?? 'Sistem' }}
        ({{ ($quote['source'] ?? 'manual') === 'auto' ? 'otomatis' : 'manual' }}).
        Pencatatan pembayaran dan koreksi belum dipasang.
    </p>
</div>

@if($invoice->status === 'void')
    <div class="iv-panel">
        <h2 class="iv-customer">Invoice dibatalkan</h2>
        <p class="iv-muted">Oleh {{ $invoice->voided_by_name }} · {{ $invoice->voided_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
        <p class="iv-reason">{{ $invoice->void_reason }}</p>
    </div>
@elseif($invoice->status === 'unpaid')
    <details class="iv-panel">
        <summary class="iv-cancel-heading">Batalkan invoice</summary>
        <form class="iv-form" method="POST" action="{{ route('invoices.void', $invoice) }}">
            @csrf
            <label for="iv-reason">Alasan pembatalan</label>
            <textarea id="iv-reason" name="reason" rows="3" minlength="5" maxlength="1000" required></textarea>
            <label class="iv-confirm">
                <input type="checkbox" name="confirmed" value="1" required>
                <span>Saya memastikan invoice ini perlu dibatalkan. Nomor dan riwayatnya tetap disimpan.</span>
            </label>
            <button class="cu-button" type="submit">Konfirmasi pembatalan</button>
        </form>
    </details>
@endif
@endsection
