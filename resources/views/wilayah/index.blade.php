<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wilayah | Tagihan dan Management Jaringan</title>
    <link rel="stylesheet" href="{{ asset('css/billing-routers.css') }}?v=1795252022983102024">
    <link rel="stylesheet" href="{{ asset('css/billing-router-dialogs.css') }}?v=1795252022983102024">
    <link id="billing-theme-css" rel="stylesheet" href="{{ asset('css/billing-theme.css') }}?v=1795252022983102024">
    <script src="{{ asset('js/billing-theme.js') }}?v=1795252022983102024"></script>
    <link rel="stylesheet" href="{{ asset('css/billing-page-header.css') }}?v=1795252767952418476">
    <link rel="stylesheet" href="{{ asset('css/billing-launcher.css') }}?v={{ filemtime(public_path('css/billing-launcher.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/billing-network-header.css') }}?v=1795253999346304957">
    <link rel="stylesheet" href="{{ asset('css/billing-users.css') }}?v={{ filemtime(public_path('css/billing-users.css')) }}">
</head>
<body>
    @include('partials.billing-page-header', ['headerTitle' => 'Wilayah'])

    <main class="router-page">
        <div class="router-page-heading">
            <h1>Wilayah</h1>
            <p>Kelompokkan pelanggan, router, dan admin per wilayah. Satu router boleh melayani beberapa wilayah.</p>
        </div>

        @if (session('status'))
            <div class="router-notice" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="router-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="router-list-toolbar">
            <p>{{ $wilayah->count() }} wilayah terdaftar.</p>
            <button type="button" class="rm-button rm-primary" id="wil-add">+ Tambah wilayah</button>
        </div>

        <div class="usr-grid">
            @foreach ($wilayah as $area)
                @php
                    $customerTotal = (int) ($customerCounts[$area->id] ?? 0);
                @endphp
                <article class="usr-card" style="--usr-tone: #2dd4bf">
                    <div class="usr-head">
                        <div class="usr-avatar" aria-hidden="true">{{ Str::upper(Str::substr($area->name, 0, 1)) }}</div>
                        <div class="usr-name">
                            <strong>{{ $area->name }}</strong>
                            <span class="wil-card-code">{{ $area->code }}</span>
                        </div>
                    </div>

                    <div class="usr-badges">
                        <span class="usr-badge" style="--b: #22d3ee">{{ $customerTotal }} pelanggan</span>
                        <span class="usr-badge" style="--b: #fb923c">{{ $area->routers->count() }} router</span>
                        <span class="usr-badge" style="--b: #60a5fa">{{ $area->users->count() }} admin</span>
                    </div>

                    <p class="wil-meta">
                        <strong>Router:</strong> {{ $area->routers->pluck('name')->join(', ') ?: '-' }}<br>
                        <strong>Admin:</strong> {{ $area->users->pluck('name')->join(', ') ?: '-' }}
                    </p>

                    <div class="usr-actions">
                        <button type="button" class="rm-button"
                            data-wil-edit="{{ json_encode([
                                'id' => $area->id,
                                'name' => $area->name,
                                'code' => $area->code,
                                'router_ids' => $area->routers->pluck('id')->values(),
                                'user_ids' => $area->users->pluck('id')->values(),
                            ]) }}">Ubah</button>

                        <form method="POST" action="{{ route('wilayah.destroy', $area) }}"
                            onsubmit="return confirm('Hapus wilayah {{ e($area->name) }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rm-button rm-danger">Hapus</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    <dialog id="wil-dialog" class="router-modal" aria-labelledby="wil-title">
        <div class="router-modal-inner">
            <div class="router-modal-heading">
                <h2 id="wil-title">Tambah wilayah</h2>
                <button type="button" class="rm-close" data-close-dialog aria-label="Tutup">&times;</button>
            </div>

            <form id="wil-form" method="POST" action="{{ route('wilayah.store') }}"
                data-store-url="{{ route('wilayah.store') }}"
                data-update-url="{{ route('wilayah.update', '__ID__') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="wil-method" disabled>
                <input type="hidden" name="_mode" id="wil-mode" value="create">
                <input type="hidden" name="_wilayah_id" id="wil-id" value="">

                <div class="router-form-row usr-row">
                    <div>
                        <label for="wil-name">Nama wilayah</label>
                        <input id="wil-name" name="name" required maxlength="100" autocomplete="off">
                    </div>
                    <div>
                        <label for="wil-code">Kode</label>
                        <input id="wil-code" name="code" required maxlength="30" autocomplete="off"
                            autocapitalize="characters" spellcheck="false">
                    </div>
                </div>

                <label>Router yang melayani wilayah ini</label>
                <div class="wil-checks">
                    @forelse ($routers as $router)
                        <label>
                            <input type="checkbox" name="router_ids[]" value="{{ $router->id }}" data-wil-router>
                            {{ $router->name }}
                        </label>
                    @empty
                        <p class="wil-empty">Belum ada router.</p>
                    @endforelse
                </div>

                <label>Admin wilayah</label>
                <div class="wil-checks">
                    @forelse ($admins as $admin)
                        <label>
                            <input type="checkbox" name="user_ids[]" value="{{ $admin->id }}" data-wil-user>
                            {{ $admin->name }} ({{ $admin->username ?: 'tanpa username' }})
                        </label>
                    @empty
                        <p class="wil-empty">Belum ada akun Admin. Buat dulu di halaman Pengguna. Super admin otomatis melihat semua wilayah.</p>
                    @endforelse
                </div>

                <div class="router-modal-actions">
                    <button class="rm-button" type="button" data-close-dialog>Batal</button>
                    <button class="router-save" type="submit">Simpan wilayah</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
    (function () {
        var dialog = document.getElementById('wil-dialog');
        var form = document.getElementById('wil-form');
        var el = function (id) { return document.getElementById(id); };

        function setChecks(selector, ids) {
            var wanted = (ids || []).map(String);
            form.querySelectorAll(selector).forEach(function (box) {
                box.checked = wanted.indexOf(box.value) !== -1;
            });
        }

        function openDialog(mode, data) {
            var edit = mode === 'edit';
            form.action = edit
                ? form.dataset.updateUrl.replace('__ID__', encodeURIComponent(data.id))
                : form.dataset.storeUrl;
            el('wil-method').disabled = !edit;
            el('wil-mode').value = mode;
            el('wil-id').value = edit ? data.id : '';
            el('wil-title').textContent = edit ? 'Ubah wilayah' : 'Tambah wilayah';
            el('wil-name').value = data.name || '';
            el('wil-code').value = data.code || '';
            setChecks('[data-wil-router]', data.router_ids);
            setChecks('[data-wil-user]', data.user_ids);
            dialog.showModal();
            document.body.classList.add('router-dialog-open');
        }

        function closeDialog() {
            if (dialog.open) dialog.close();
        }

        dialog.addEventListener('close', function () {
            document.body.classList.remove('router-dialog-open');
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });
        dialog.querySelectorAll('[data-close-dialog]').forEach(function (button) {
            button.addEventListener('click', closeDialog);
        });

        el('wil-add').addEventListener('click', function () { openDialog('create', {}); });

        document.querySelectorAll('[data-wil-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDialog('edit', JSON.parse(button.dataset.wilEdit));
            });
        });

        @if ($errors->any() && old('_mode'))
        @php
            $wilReopen = [
                'id' => old('_wilayah_id'),
                'name' => old('name'),
                'code' => old('code'),
                'router_ids' => old('router_ids', []),
                'user_ids' => old('user_ids', []),
            ];
            $wilMode = old('_mode');
        @endphp
        openDialog(@json($wilMode), @json($wilReopen));
        @endif
    })();
    </script>

    @include('partials.billing-launcher')
    <script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
