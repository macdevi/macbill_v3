@extends('invoices.layout')
@section('title', 'Titip Saldo')
@section('content')
<div class="iv-heading">
    <div><h1>Titip Saldo</h1><p>Cari pelanggan, lalu klik untuk mencatat titip saldo · zona waktu WIB</p></div>
</div>

<div class="iv-cards">
    <div class="iv-panel"><span class="iv-muted">Total titip saldo</span><strong>Rp{{ number_format($totalSaldo, 0, ',', '.') }}</strong></div>
</div>

<form class="iv-search" method="GET" action="{{ route('balances.index') }}">
    <input type="search" name="q" value="{{ request('q') }}" maxlength="150"
        placeholder="Cari nama atau kode pelanggan..." aria-label="Cari pelanggan" autofocus>
    <button class="cu-button" type="submit">Cari</button>
</form>

@forelse($customers as $c)
    <a class="iv-panel" href="{{ route('balances.show', $c) }}" style="display:block;text-decoration:none;color:inherit">
        <div class="iv-row">
            <span class="iv-number">{{ $c->code }}</span>
            <span class="iv-muted">Titip saldo</span>
        </div>
        <div class="iv-row">
            <h2 class="iv-customer">{{ $c->name }}</h2>
            <strong>Rp{{ number_format((int) ($balances->get($c->id)?->saldo ?? 0), 0, ',', '.') }}</strong>
        </div>
    </a>
@empty
    <div class="cu-empty"><h2>Pelanggan tidak ditemukan</h2></div>
@endforelse
@endsection
