@extends('invoices.layout')
@section('title', 'Titip Saldo')
@section('content')
<div class="iv-heading">
    <div><h1>Titip saldo</h1><p>{{ $customer->code }} · {{ $customer->name }}</p></div>
    <a class="cu-button" href="{{ route('balances.index') }}">Kembali</a>
</div>

<div class="iv-cards">
    <div class="iv-panel"><span class="iv-muted">Titip saldo</span><strong>Rp{{ number_format($balance, 0, ',', '.') }}</strong></div>
    <div class="iv-panel"><span class="iv-muted">Tunggakan</span><strong>Rp{{ number_format($due, 0, ',', '.') }}</strong></div>
</div>

@if($due > 0)
    <p class="iv-muted">Pelanggan ini punya tunggakan. Setoran titip saldo langsung dipakai melunasi tunggakan, mulai dari invoice tertua.</p>
    @if($balance > 0)
        <form method="POST" action="{{ route('balances.apply', $customer) }}" style="margin-bottom:16px">
            @csrf
            <button class="cu-button cu-primary" type="submit">Pakai saldo untuk tunggakan</button>
        </form>
    @endif
@endif

<form class="iv-panel iv-form" method="POST" action="{{ route('balances.deposit', $customer) }}">
    @csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
    <h2 class="iv-customer">Tambah titip saldo</h2>

    <label for="dep-amount">Nominal (Rp)</label>
    <input id="dep-amount" type="number" name="amount" min="1" max="1000000000" step="1" inputmode="numeric" required value="{{ old('amount') }}">

    <label for="dep-date">Tanggal diterima</label>
    <input id="dep-date" type="date" name="paid_on" required
        value="{{ old('paid_on', \App\Services\InvoicePeriod::today()->format('Y-m-d')) }}"
        max="{{ \App\Services\InvoicePeriod::today()->format('Y-m-d') }}">

    <label for="dep-method">Metode</label>
    <select id="dep-method" name="method" required>
        <option value="cash" @selected(old('method') === 'cash')>Tunai</option>
        <option value="transfer" @selected(old('method') === 'transfer')>Transfer bank</option>
    </select>

    <label for="dep-ref">Referensi atau catatan (opsional)</label>
    <input id="dep-ref" type="text" name="reference" maxlength="120" value="{{ old('reference') }}">

    <label class="iv-confirm">
        <input type="checkbox" name="confirmed" value="1" required>
        <span>Saya sudah memastikan uang ini benar-benar diterima.</span>
    </label>
    <button class="cu-button cu-primary" type="submit">Simpan titip saldo</button>
</form>

<h2 class="iv-customer" style="margin-top:28px">Riwayat saldo</h2>
@forelse($entries as $e)
    <article class="iv-panel">
        <div class="iv-row">
            <span class="iv-number">
                @if($e->type === 'in')
                    {{ $e->receipt?->kind === 'deposit' ? 'Setoran titip saldo' : 'Kelebihan pembayaran' }}
                @else
                    Dipakai untuk {{ $e->invoice?->number ?? '-' }}
                @endif
            </span>
            <span class="iv-badge">{{ $e->status === 'void' ? 'Dibatalkan' : 'Aktif' }}</span>
        </div>
        <div class="iv-row">
            <span class="iv-muted">{{ $e->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · oleh {{ $e->created_by_name }}</span>
            <strong>{{ $e->amount < 0 ? '-' : '+' }}Rp{{ number_format(abs($e->amount), 0, ',', '.') }}</strong>
        </div>
        @if($e->status === 'void')
            <p class="iv-muted">Dibatalkan oleh {{ $e->voided_by_name }}: {{ $e->void_reason }}</p>
        @elseif($e->type === 'use')
            @include('payments._void', ['action' => route('balances.void-use', $e), 'id' => 'e'.$e->id, 'label' => 'Batalkan pemakaian saldo'])
        @elseif($e->receipt?->kind === 'deposit' && $e->receipt->status === 'active')
            @include('payments._void', ['action' => route('payments.void', $e->receipt), 'id' => 'd'.$e->id, 'label' => 'Batalkan setoran'])
        @else
            <p class="iv-muted">Koreksi lewat Tagihan, buka Rincian invoice, lalu Lihat riwayat pembayaran.</p>
        @endif
    </article>
@empty
    <div class="cu-empty"><h2>Belum ada riwayat saldo</h2></div>
@endforelse
@endsection
