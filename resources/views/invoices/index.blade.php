@extends('invoices.layout')
@section('title', 'Tagihan')
@section('content')
<section class="iv-panel iv-recap" aria-label="Ringkasan tagihan">
    <div>
        <span class="iv-muted">Belum dibayar</span>
        <strong>Rp{{ number_format($summary['unpaid_total'], 0, ',', '.') }}</strong>
        <small class="iv-muted">{{ $summary['unpaid_count'] }} invoice</small>
    </div>
    <div>
        <span class="iv-muted">Terbit bulan ini</span>
        <strong>Rp{{ number_format($summary['month_total'], 0, ',', '.') }}</strong>
        <small class="iv-muted">{{ $summary['month_count'] }} invoice</small>
    </div>
</section>

<form class="iv-search" method="GET" action="{{ route('invoices.index') }}">
    <input type="search" name="q" value="{{ request('q') }}" maxlength="150"
        placeholder="Cari invoice atau pelanggan..." aria-label="Cari invoice">
    <select name="status" aria-label="Status invoice">
        <option value="open" @selected($statusFilter === 'open')>Belum dibayar</option>
        <option value="" @selected($statusFilter === '')>Semua status</option>
        <option value="partial" @selected($statusFilter === 'partial')>Dibayar sebagian</option>
        <option value="paid" @selected($statusFilter === 'paid')>Lunas</option>
        <option value="void" @selected($statusFilter === 'void')>Dibatalkan</option>
    </select>
    <button class="cu-button" type="submit">Cari</button>
</form>

@forelse($invoices as $invoice)
    <article class="iv-panel iv-invoice-card">
        <div class="iv-invoice-head">
            <h2 class="iv-invoice-name">{{ $invoice->customer_name }} @if(isset(\App\Models\Customer::trashedIds()[$invoice->customer_id]))<small style="font-size:.7em;font-weight:500;padding:2px 8px;border-radius:999px;background:rgba(220,38,38,.18);border:1px solid #dc2626;white-space:nowrap">Pelanggan dihapus</small>@endif</h2>
            <strong class="iv-invoice-total">Rp{{ number_format($invoice->total, 0, ',', '.') }}</strong>
        </div>

        <div class="iv-invoice-meta">
            <span class="iv-number">{{ $invoice->number }}</span>
            <span class="iv-badge iv-payment-status iv-payment-status-{{ $invoice->status }}">{{ $invoice->status_label }}</span>
        </div>

        <div class="iv-invoice-dates">
            <p>Periode <span>{{ $invoice->period_start->format('d/m/Y') }} – {{ $invoice->period_end->format('d/m/Y') }}</span></p>
            <p>Jatuh tempo <span>{{ $invoice->due_on->format('d/m/Y') }}</span></p>
            <p class="iv-invoice-issued">Terbit <span>{{ $invoice->issued_on->format('d/m/Y') }}</span></p>
        </div>

        @if($invoice->paid_amount > 0)
            <div class="iv-invoice-payment">
                <span>Dibayar Rp{{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
                <span>Sisa Rp{{ number_format($invoice->remaining, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="iv-actions iv-invoice-actions">
            <button type="button" class="cu-button cu-primary" data-iv-open="iv-dialog-{{ $invoice->id }}">Rincian</button>
            @include('invoices._modal')
        </div>
    </article>
@empty
    <div class="cu-empty">
        <h2>Belum ada invoice yang sesuai</h2>
        <p>Generate tagihan tersedia di menu Pengaturan.</p>
    </div>
@endforelse

<div class="cu-pagination">
    @if($invoices->previousPageUrl())<a class="cu-button" href="{{ $invoices->previousPageUrl() }}">Sebelumnya</a>@endif
    <span>Halaman {{ $invoices->currentPage() }} / {{ $invoices->lastPage() }}</span>
    @if($invoices->nextPageUrl())<a class="cu-button" href="{{ $invoices->nextPageUrl() }}">Berikutnya</a>@endif
</div>

<script>
document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-iv-open]');
    if (opener) {
        var dialog = document.getElementById(opener.dataset.ivOpen);
        if (dialog && dialog.showModal) dialog.showModal();
        return;
    }
    if (event.target.closest('[data-iv-close]')) {
        event.target.closest('dialog').close();
        return;
    }
    if (
        event.target.tagName === 'DIALOG'
        && event.target.id !== 'iv-loading-dialog'
    ) {
        event.target.close();
    }
});
</script>

<dialog id="iv-wa-dialog" class="iv-dialog" aria-labelledby="iv-wa-title">
    <div class="iv-dialog-head">
        <div><h2 id="iv-wa-title">Kirim tagihan lewat WhatsApp</h2></div>
        <button type="button" class="iv-dialog-x" data-iv-close aria-label="Tutup">&times;</button>
    </div>
    <div class="iv-dialog-body">
        <p class="iv-muted">Pelanggan: <strong data-f="name"></strong></p>
        <p class="iv-muted">Nomor tujuan: <strong data-f="phone"></strong></p>
        <p class="iv-help" data-f="warn" hidden>Nomor WhatsApp pelanggan kosong atau formatnya tidak valid. Perbaiki di data pelanggan.</p>
        <p class="iv-muted">Pesan</p>
        <pre class="iv-reason" data-f="message"></pre>
        <p class="iv-help">Tombol di bawah hanya membuka WhatsApp dengan pesan terisi. Pesan belum terkirim sampai Anda menekan Kirim di WhatsApp.</p>
    </div>
    <div class="iv-dialog-foot">
        <button type="button" class="cu-button" data-iv-close>Tutup</button>
        <a class="cu-button cu-primary" data-f="link" href="#" target="_blank" rel="noopener noreferrer">Buka WhatsApp</a>
    </div>
</dialog>

<script>
document.addEventListener('click', function (event) {
    var wa = event.target.closest('[data-iv-wa]');
    if (wa) {
        var w = document.getElementById('iv-wa-dialog');
        var link = w.querySelector('[data-f=link]');
        w.querySelector('[data-f=name]').textContent = wa.dataset.name;
        w.querySelector('[data-f=phone]').textContent = wa.dataset.phone ? '+' + wa.dataset.phone : '-';
        w.querySelector('[data-f=message]').textContent = wa.dataset.message;
        w.querySelector('[data-f=warn]').hidden = !!wa.dataset.url;
        link.href = wa.dataset.url || '#';
        link.style.display = wa.dataset.url ? '' : 'none';
        w.showModal();
    }
});
</script>
<dialog id="iv-pay-dialog" class="iv-dialog" aria-labelledby="iv-pay-title">
    <form method="POST" action="{{ route('payments.store') }}" class="iv-form iv-dialog-form">
        @csrf
        <input type="hidden" name="customer_id" value="">
        <input type="hidden" name="request_key" value="{{ old('request_key', $payKey) }}">
        <div class="iv-dialog-head">
            <div><h2 id="iv-pay-title">Catat pembayaran</h2></div>
            <button type="button" class="iv-dialog-x" data-iv-close aria-label="Tutup">&times;</button>
        </div>
        <div class="iv-dialog-body">
            <p class="iv-muted">Pelanggan: <strong data-f="name"></strong></p>
            <p class="iv-muted">Uang dialokasikan ke invoice yang dicentang lebih dulu, lalu ke invoice tertua. Titip saldo hanya terbentuk jika semua invoice terbuka sudah lunas.</p>
            <div data-f="invoices"></div>

            <label for="pay-amount">Nominal diterima (Rp)</label>
            <input id="pay-amount" type="number" name="amount" min="1" max="1000000000" step="1" inputmode="numeric" required>

            <div class="iv-panel iv-preview" id="pay-preview" aria-live="polite"></div>

            <label for="pay-date">Tanggal pembayaran</label>
            <input id="pay-date" type="date" name="paid_on" required
                value="{{ \App\Services\InvoicePeriod::today()->format('Y-m-d') }}"
                max="{{ \App\Services\InvoicePeriod::today()->format('Y-m-d') }}">

            <label for="pay-method">Metode</label>
            <select id="pay-method" name="method" required>
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer bank</option>
            </select>

            <label for="pay-ref">Referensi atau catatan (opsional)</label>
            <input id="pay-ref" type="text" name="reference" maxlength="120">

            <input type="hidden" name="confirmed" value="1">
        </div>
        <div class="iv-dialog-foot">
            <button type="button" class="cu-button" data-iv-close>Tutup</button>
            <button type="submit" class="cu-button cu-primary">Simpan pembayaran</button>
        </div>
    </form>
</dialog>

<script>
window.IV_OPEN = @json($openByCustomer);
(function () {
    var current = [];
    var dialog = document.getElementById('iv-pay-dialog');
    var amt = document.getElementById('pay-amount');
    var box = document.getElementById('pay-preview');
    var fmt = function (n) { return 'Rp' + Number(n).toLocaleString('id-ID'); };

    function render() {
        var prio = {};
        dialog.querySelectorAll('[name="priority_ids[]"]:checked').forEach(function (c) { prio[c.value] = true; });
        var ordered = current.filter(function (i) { return prio[i.id]; })
            .concat(current.filter(function (i) { return !prio[i.id]; }));
        var rem = parseInt(amt.value, 10) || 0;
        box.textContent = '';
        if (rem <= 0) { box.textContent = 'Isi nominal untuk melihat alokasi.'; return; }

        var ul = document.createElement('ul');
        ordered.forEach(function (i) {
            if (rem <= 0) return;
            var take = Math.min(i.due, rem);
            rem -= take;
            var li = document.createElement('li');
            li.textContent = i.number + ': ' + fmt(take) + (take < i.due ? ' (sisa ' + fmt(i.due - take) + ')' : ' (lunas)');
            ul.appendChild(li);
        });
        box.appendChild(ul);
        if (rem > 0) {
            var p = document.createElement('p');
            p.textContent = 'Titip saldo: ' + fmt(rem);
            box.appendChild(p);
        }
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-iv-pay]');
        if (!btn) return;

        current = (window.IV_OPEN && window.IV_OPEN[btn.dataset.customer]) || [];
        dialog.querySelector('[name=customer_id]').value = btn.dataset.customer;
        dialog.querySelector('[data-f=name]').textContent = btn.dataset.name;
        amt.value = '';

        var dateInput = dialog.querySelector('[name=paid_on]');
        dateInput.value = dateInput.defaultValue;
        dialog.querySelector('[name=method]').value = 'cash';
        dialog.querySelector('[name=reference]').value = '';

        var list = dialog.querySelector('[data-f=invoices]');
        list.textContent = '';
        current.forEach(function (i) {
            var label = document.createElement('label');
            label.className = 'iv-confirm';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'priority_ids[]';
            cb.value = i.id;
            cb.checked = String(i.id) === btn.dataset.invoice;
            cb.addEventListener('change', render);
            var span = document.createElement('span');
            span.textContent = i.number + ' · Sisa ' + fmt(i.due);
            label.appendChild(cb);
            label.appendChild(span);
            list.appendChild(label);
        });

        render();
        dialog.showModal();
    });

    amt.addEventListener('input', render);
})();
</script>
<dialog id="iv-loading-dialog" class="iv-loading" aria-label="Mencatat pembayaran">
    <div class="iv-loading-box" role="status" aria-live="polite">
        <span class="iv-spinner" aria-hidden="true"></span>
        <strong>Mencatat pembayaran...</strong>
        <span>Mohon tunggu sebentar</span>
    </div>
</dialog>

<dialog id="iv-nota-dialog" class="iv-dialog" aria-labelledby="iv-nota-title">
    <div class="iv-dialog-head">
        <div><h2 id="iv-nota-title">Nota pembayaran</h2></div>
        <button type="button" class="iv-dialog-x" data-iv-close aria-label="Tutup">&times;</button>
    </div>
    <div class="iv-dialog-body">
        <pre class="iv-reason" data-f="text" style="white-space:pre-wrap"></pre>
        <p class="iv-help" data-f="warn" hidden>Nomor WhatsApp pelanggan kosong atau tidak valid. Nota tetap bisa dicetak.</p>
    </div>
    <div class="iv-dialog-foot">
        <button type="button" class="cu-button" data-iv-close>Tutup</button>
        <button type="button" class="cu-button" data-iv-print>Cetak</button>
        <a class="cu-button cu-primary" data-f="link" href="#" target="_blank" rel="noopener noreferrer">Kirim WhatsApp</a>
    </div>
</dialog>

<script>
(function () {
    var payDialog = document.getElementById('iv-pay-dialog');
    var form = payDialog.querySelector('form');
    var submitBtn = form.querySelector('[type=submit]');
    var loadingDlg = document.getElementById('iv-loading-dialog');
    var notaDlg = document.getElementById('iv-nota-dialog');
    var notaText = '';
    var saving = false;

    function resetSaving() {
        saving = false;
        submitBtn.disabled = false;
        if (loadingDlg.open) loadingDlg.close();
    }
    window.addEventListener('pageshow', resetSaving);
    loadingDlg.addEventListener('cancel', function (event) { event.preventDefault(); });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-iv-pay]')) resetSaving();

        var nota = event.target.closest('[data-iv-nota]');
        if (nota) { openNota(nota.dataset.ivNota); return; }

        if (event.target.closest('[data-iv-print]')) { printNota(notaText); }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (saving) return;
        saving = true;
        submitBtn.disabled = true;
        loadingDlg.showModal();
        setTimeout(function () { form.submit(); }, 1400);
    });

    function openNota(id) {
        fetch('{{ url('/payments') }}/' + encodeURIComponent(id) + '/nota', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (d) {
            notaText = d.text;
            notaDlg.querySelector('[data-f=text]').textContent = d.text;
            var link = notaDlg.querySelector('[data-f=link]');
            link.href = d.url || '#';
            link.style.display = d.url ? '' : 'none';
            notaDlg.querySelector('[data-f=warn]').hidden = !!d.url;
            notaDlg.showModal();
        })
        .catch(function () { alert('Nota tidak dapat dimuat. Coba lagi.'); });
    }

    function printNota(text) {
        var frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
        document.body.appendChild(frame);
        var doc = frame.contentWindow.document;
        doc.open();
        doc.write('<!doctype html><title>Nota</title>');
        doc.close();
        var style = doc.createElement('style');
        style.textContent = 'body{margin:0;padding:12px}pre{font:13px/1.5 monospace;white-space:pre-wrap;margin:0}@page{margin:8mm}';
        doc.head.appendChild(style);
        var pre = doc.createElement('pre');
        pre.textContent = text;
        doc.body.appendChild(pre);
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(function () { document.body.removeChild(frame); }, 1500);
    }

    @if(session('nota_receipt'))
    openNota({{ (int) session('nota_receipt') }});
    @endif
})();
</script>
@include('invoices._history_dialog')
@include('invoices._invoice_notes_dialog')
@endsection
