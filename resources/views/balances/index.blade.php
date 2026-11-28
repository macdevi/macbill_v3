@extends('invoices.layout')
@section('title', 'Titip Saldo')
@section('content')
<div class="iv-heading">
    <div><h1>Titip Saldo</h1><p>Pelanggan yang memiliki titip saldo · zona waktu WIB</p></div>
    <button type="button" class="cu-button cu-primary" data-bal-open="bal-add">+ Titip saldo</button>
</div>

<div class="iv-cards">
    <div class="iv-panel"><span class="iv-muted">Total titip saldo</span><strong>Rp{{ number_format($totalSaldo, 0, ',', '.') }}</strong></div>
</div>

<form class="iv-search" method="GET" action="{{ route('balances.index') }}">
    <input type="search" name="q" value="{{ $q }}" maxlength="150"
        placeholder="Cari pelanggan yang punya saldo..." aria-label="Cari pelanggan">
    <button class="cu-button" type="submit">Cari</button>
</form>

@forelse($customers as $c)
    <a class="iv-panel bal-result" href="{{ route('balances.show', $c) }}">
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
    <div class="cu-empty">
        <h2>{{ $q !== '' ? 'Pelanggan tidak ditemukan' : 'Belum ada pelanggan dengan titip saldo' }}</h2>
        <p>Gunakan tombol + Titip saldo untuk mencatat setoran.</p>
    </div>
@endforelse

<div class="cu-pagination">
    @if($customers->previousPageUrl())<a class="cu-button" href="{{ $customers->previousPageUrl() }}">Sebelumnya</a>@endif
    <span>Halaman {{ $customers->currentPage() }} / {{ $customers->lastPage() }}</span>
    @if($customers->nextPageUrl())<a class="cu-button" href="{{ $customers->nextPageUrl() }}">Berikutnya</a>@endif
</div>

<dialog id="bal-add" class="iv-dialog" aria-labelledby="bal-add-title">
    <div class="iv-dialog-head">
        <div><h2 id="bal-add-title">Tambah titip saldo</h2></div>
        <button type="button" class="iv-dialog-x" data-bal-close aria-label="Tutup">&times;</button>
    </div>
    <div class="iv-dialog-body">
        <label for="bal-q">Cari pelanggan</label>
        <input id="bal-q" type="search" maxlength="150" autocomplete="off"
            placeholder="Ketik nama atau kode pelanggan...">
        <p class="iv-muted" id="bal-status" aria-live="polite"></p>
        <div id="bal-results"></div>
    </div>
    <div class="iv-dialog-foot">
        <button type="button" class="cu-button" data-bal-close>Tutup</button>
    </div>
</dialog>

<script>
(function () {
    var dialog = document.getElementById('bal-add');
    var input = document.getElementById('bal-q');
    var box = document.getElementById('bal-results');
    var note = document.getElementById('bal-status');
    var url = '{{ route('balances.search') }}';
    var seq = 0;
    var timer = null;
    var fmt = function (n) { return 'Rp' + Number(n).toLocaleString('id-ID'); };

    function row(cls, tag, text) {
        var el = document.createElement(tag);
        el.className = cls;
        el.textContent = text;
        return el;
    }

    function render(rows) {
        box.textContent = '';
        rows.forEach(function (r) {
            var a = document.createElement('a');
            a.className = 'iv-panel bal-result';
            a.href = r.url;

            var top = document.createElement('div');
            top.className = 'iv-row';
            top.appendChild(row('iv-number', 'span', r.code || ''));
            top.appendChild(row('iv-muted', 'span', 'Titip saldo'));

            var bottom = document.createElement('div');
            bottom.className = 'iv-row';
            bottom.appendChild(row('iv-customer', 'h2', r.name));
            bottom.appendChild(row('', 'strong', fmt(r.saldo)));

            a.appendChild(top);
            a.appendChild(bottom);
            box.appendChild(a);
        });
        note.textContent = rows.length === 0
            ? 'Pelanggan tidak ditemukan.'
            : (rows.length >= 30 ? 'Menampilkan 30 pelanggan pertama. Ketik untuk mempersempit.' : '');
    }

    function load() {
        var mine = ++seq;
        note.textContent = 'Memuat...';
        fetch(url + '?q=' + encodeURIComponent(input.value.trim()), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (d) { if (mine === seq) render(d.data || []); })
        .catch(function () {
            if (mine !== seq) return;
            box.textContent = '';
            note.textContent = 'Gagal memuat. Coba lagi.';
        });
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-bal-open]')) {
            input.value = '';
            dialog.showModal();
            load();
            input.focus();
            return;
        }
        if (event.target.closest('[data-bal-close]') || event.target === dialog) dialog.close();
    });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });
})();
</script>
@endsection
