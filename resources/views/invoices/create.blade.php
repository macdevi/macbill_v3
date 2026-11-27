@extends('invoices.layout')
@section('title', 'Generate Tagihan')
@section('content')
<div class="iv-heading">
    <div>
        <h1>Generate tagihan</h1>
        <p>Pilih bulan terbit. Periode pemakaian dihitung otomatis dari tanggal tagihan tiap pelanggan.</p>
    </div>
    <a class="cu-button" href="{{ route('invoices.index') }}">Kembali</a>
</div>

<form class="iv-panel iv-form" method="GET" action="{{ route('invoices.create') }}">
    <label for="iv-period">Bulan terbit</label>
    <input id="iv-period" type="month" name="period" value="{{ $period }}" max="{{ $current }}" min="2000-01" onchange="this.form.submit()">
    <p class="iv-help">
        Contoh tanggal tagihan 15 dengan bulan terbit November: invoice terbit 15 November untuk pemakaian 15 Oktober sampai 14 November.
        Sistem tidak membaca riwayat pembayaran dari sistem lama.
    </p>
</form>

@if(count($eligible))
<form method="POST" action="{{ route('invoices.store') }}">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    <div class="iv-panel">
        <label class="iv-confirm">
            <input type="checkbox" id="iv-all" checked>
            <span>Pilih semua ({{ count($eligible) }} pelanggan)</span>
        </label>
        @foreach($eligible as $q)
            <label class="iv-confirm iv-pick">
                <input type="checkbox" name="customer_ids[]" value="{{ $q['customer_id'] }}" checked>
                <span>
                    <strong>{{ $q['customer_name'] }}</strong> · {{ $q['customer_code'] }} · {{ $q['package_name'] }}<br>
                    <span class="iv-muted">
                        Pemakaian {{ \Illuminate\Support\Carbon::parse($q['period_start'])->format('d/m/Y') }}
                        sampai {{ \Illuminate\Support\Carbon::parse($q['period_end'])->format('d/m/Y') }}
                        · Terbit {{ \Illuminate\Support\Carbon::parse($q['issued_on'])->format('d/m/Y') }}
                        · Rp{{ number_format($q['total'], 0, ',', '.') }}
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    <div class="iv-panel iv-form">
        <label class="iv-confirm">
            <input type="checkbox" name="confirmed" value="1" required>
            <span>Saya sudah memeriksa daftar. Pelanggan yang tidak perlu ditagih sudah tidak saya centang.</span>
        </label>
        <p class="iv-help">Tindakan ini hanya menerbitkan invoice. Tidak ada pembayaran, perubahan layanan, atau tindakan MikroTik.</p>
        <button class="cu-button cu-primary" type="submit">Terbitkan invoice terpilih</button>
    </div>
</form>
<script>
document.getElementById('iv-all')?.addEventListener('change', function () {
    document.querySelectorAll('input[name="customer_ids[]"]').forEach(function (box) { box.checked = this.checked; }, this);
});
</script>
@else
    <div class="cu-empty">
        <h2>Tidak ada pelanggan yang perlu ditagih</h2>
        <p>Semua pelanggan sudah punya invoice bulan ini atau dilewati. Lihat alasan di bawah.</p>
    </div>
@endif

@if(count($skipped))
<details class="iv-panel">
    <summary class="iv-muted">Dilewati ({{ count($skipped) }})</summary>
    @foreach($skipped as $row)
        <p class="iv-muted"><strong>{{ $row['customer']->name }}</strong> · {{ $row['customer']->code }}<br>{{ $row['reason'] }}</p>
    @endforeach
</details>
@endif
@endsection
