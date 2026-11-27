@extends('invoices.layout')
@section('title', 'Pengeluaran')
@section('content')
@php
    $rupiah = fn ($n) => 'Rp'.number_format((int) $n, 0, ',', '.');
    $monthText = substr($month, 5, 2).'/'.substr($month, 0, 4);
    $todayDate = \App\Services\InvoicePeriod::today()->format('Y-m-d');
    $reopenCreate = (bool) old('request_key');
@endphp

@php
    $expenseMonthNames = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];
    $expenseMonthLabel = $expenseMonthNames[substr($month, 5, 2)]
        .' '.substr($month, 0, 4);
@endphp

<section class="iv-panel ex-month-card" aria-label="Ringkasan pengeluaran bulanan">
    <div class="ex-month-head">
        <div>
            <h2>Pengeluaran bulan ini</h2>
            <p>{{ $expenseMonthLabel }}</p>
        </div>

        <form method="GET" action="{{ route('expenses.index') }}"
            class="ex-month-picker">
            <label for="ex-selected-month">Pilih bulan</label>
            <input id="ex-selected-month"
                type="month"
                name="month"
                value="{{ $month }}"
                required
                onchange="this.form.requestSubmit()">
            <noscript>
                <button type="submit" class="cu-button">Tampilkan</button>
            </noscript>
        </form>
    </div>

    <div class="ex-month-total">{{ $rupiah($activeTotal) }}</div>
    <p class="ex-month-note">
        {{ $activeCount }} transaksi aktif
    </p>
</section>

<div class="ex-toolbar">
    <button type="button" class="cu-button cu-primary"
        data-ex-open="ex-create">Tambah pengeluaran</button>
</div>

@forelse($expenses as $expense)
    @php
        $isVoid = $expense->status === 'void';
        $recordedAt = $expense->created_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');
    @endphp
    <article class="iv-panel ex-item {{ $isVoid ? 'is-void' : '' }}">
        <div class="ex-item-top">
            <strong class="ex-item-amount">{{ $rupiah($expense->amount) }}</strong>
            <span class="iv-badge {{ $isVoid ? 'ex-badge-void' : 'ex-badge-active' }}">{{ $isVoid ? 'Dibatalkan' : 'Aktif' }}</span>
        </div>
        <p class="ex-item-meta">
            {{ $expense->spent_on->format('d/m/Y') }}
            · {{ $expense->category_label }}
            · {{ $expense->method_label }}
        </p>
        <p class="ex-item-desc">{{ $expense->description }}</p>
        <p class="iv-muted">Dicatat oleh {{ $expense->recorded_by_name }}</p>
        <button type="button" class="cu-button cu-primary" data-ex-open="ex-dialog-{{ $expense->id }}">Rincian</button>

        <dialog id="ex-dialog-{{ $expense->id }}" class="iv-dialog ex-dialog"
            aria-labelledby="ex-title-{{ $expense->id }}">
            <div class="iv-dialog-head">
                <div>
                    <h2 id="ex-title-{{ $expense->id }}">{{ $rupiah($expense->amount) }}</h2>
                    <span class="iv-badge {{ $isVoid ? 'ex-badge-void' : 'ex-badge-active' }}">{{ $isVoid ? 'Dibatalkan' : 'Aktif' }}</span>
                </div>
                <button type="button" class="iv-dialog-x" data-ex-close aria-label="Tutup">&times;</button>
            </div>

            <div class="iv-dialog-body">
                <dl class="iv-summary">
                    <div><dt>Tanggal</dt><dd>{{ $expense->spent_on->format('d/m/Y') }}</dd></div>
                    <div><dt>Kategori</dt><dd>{{ $expense->category_label }}</dd></div>
                    <div><dt>Metode</dt><dd>{{ $expense->method_label }}</dd></div>
                    <div><dt>Dicatat oleh</dt><dd>{{ $expense->recorded_by_name }}</dd></div>
                    <div class="iv-wide"><dt>Waktu pencatatan</dt><dd>{{ $recordedAt ?? '-' }} WIB</dd></div>
                    <div class="iv-wide"><dt>Keterangan</dt><dd class="ex-pre">{{ $expense->description }}</dd></div>
                </dl>

                @if($isVoid)
                    <div class="iv-panel">
                        <h3 class="iv-customer">Pengeluaran dibatalkan</h3>
                        <p class="iv-muted">
                            Oleh {{ $expense->voided_by_name ?? '-' }}
                            · {{ $expense->voided_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} WIB
                        </p>
                        <p class="iv-reason">{{ $expense->void_reason ?? '-' }}</p>
                    </div>
                @else
                    <div class="iv-panel">
                        @include('payments._void', [
                            'action' => route('expenses.void', $expense),
                            'id' => 'ex'.$expense->id,
                            'label' => 'Batalkan pengeluaran',
                        ])
                    </div>
                @endif
            </div>

            <div class="iv-dialog-foot">
                <button type="button" class="cu-button" data-ex-close>Tutup</button>
            </div>
        </dialog>
    </article>
@empty
    <div class="cu-empty">
        <h2>Belum ada pengeluaran</h2>
        <p>Tidak ada catatan yang sesuai dengan filter ini.</p>
    </div>
@endforelse

<div class="cu-pagination">
    @if($expenses->previousPageUrl())<a class="cu-button" href="{{ $expenses->previousPageUrl() }}">Sebelumnya</a>@endif
    <span>Halaman {{ $expenses->currentPage() }} / {{ $expenses->lastPage() }}</span>
    @if($expenses->nextPageUrl())<a class="cu-button" href="{{ $expenses->nextPageUrl() }}">Berikutnya</a>@endif
</div>

<dialog id="ex-create" class="iv-dialog ex-dialog" aria-labelledby="ex-create-title">
    <form method="POST" action="{{ route('expenses.store') }}"
        class="iv-form iv-dialog-form" id="ex-create-form">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
        <div class="iv-dialog-head">
            <div><h2 id="ex-create-title">Tambah pengeluaran</h2></div>
            <button type="button" class="iv-dialog-x" data-ex-close aria-label="Tutup">&times;</button>
        </div>
        <div class="iv-dialog-body">
            @if($reopenCreate && $errors->any())
                <div class="cu-error" role="alert"><ul>
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
            @endif

            <label for="ex-wilayah">Wilayah</label>
            <select id="ex-wilayah" name="wilayah_id" required>
                @if($wilayahList->count() !== 1)<option value="">Pilih wilayah</option>@endif
                @foreach($wilayahList as $area)
                    <option value="{{ $area->id }}" @selected((string) old('wilayah_id') === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>

            <label for="ex-date">Tanggal pengeluaran</label>
            <input id="ex-date" type="date" name="spent_on" required
                value="{{ old('spent_on', $todayDate) }}" max="{{ $todayDate }}">

            <label for="ex-category">Kategori</label>
            <select id="ex-category" name="category" required>
                @foreach(\App\Models\Expense::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="ex-amount">Nominal (Rp)</label>
            <input id="ex-amount" type="number" name="amount" min="1" max="1000000000"
                step="1" inputmode="numeric" required value="{{ old('amount') }}">

            <label for="ex-method">Metode pembayaran</label>
            <select id="ex-method" name="method" required>
                <option value="cash" @selected(old('method', 'cash') === 'cash')>Tunai</option>
                <option value="transfer" @selected(old('method') === 'transfer')>Transfer bank</option>
            </select>

            <label for="ex-description">Keterangan</label>
            <textarea id="ex-description" name="description" rows="3"
                minlength="3" maxlength="500" required>{{ old('description') }}</textarea>
        </div>
        <div class="iv-dialog-foot">
            <button type="button" class="cu-button" data-ex-close>Tutup</button>
            <button type="submit" class="cu-button cu-primary">Simpan pengeluaran</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-ex-open]');
        if (opener) {
            var dialog = document.getElementById(opener.dataset.exOpen);
            if (dialog && dialog.showModal && !dialog.open) dialog.showModal();
            return;
        }
        if (event.target.closest('[data-ex-close]')) {
            event.target.closest('dialog').close();
            return;
        }
        if (event.target.tagName === 'DIALOG') event.target.close();
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.closest('.ex-dialog')) return;
        if (form.dataset.sending === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.sending = '1';
        var button = form.querySelector('[type=submit]');
        if (button) button.disabled = true;
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.ex-dialog form').forEach(function (form) {
            form.dataset.sending = '0';
            var button = form.querySelector('[type=submit]');
            if (button) button.disabled = false;
        });
    });

    @if($reopenCreate)
    var create = document.getElementById('ex-create');
    if (create && create.showModal) create.showModal();
    @endif
})();
</script>
@endsection
