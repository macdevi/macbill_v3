<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengguna | Tagihan dan Management Jaringan</title>
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
    @include('partials.billing-page-header', ['headerTitle' => 'Pengguna'])

    <main class="router-page">
        <div class="router-page-heading">
            <h1>Pengguna</h1>
            <p>Kelola akun Super admin dan Admin. Hanya Super admin yang dapat membuka halaman ini.</p>
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
            <p>{{ $users->count() }} akun terdaftar.</p>
            <button type="button" class="rm-button rm-primary" id="usr-add">+ Tambah pengguna</button>
        </div>

        <div class="usr-grid">
            @foreach ($users as $account)
                @php
                    $active = (bool) ($account->is_active ?? true);
                    $isSuper = $account->role === 'super_admin';
                    $isMe = (int) $account->id === (int) auth()->id();
                    $tone = $isSuper ? '#fbbf24' : '#60a5fa';
                @endphp
                <article class="usr-card {{ $active ? '' : 'is-off' }}" style="--usr-tone: {{ $tone }}">
                    <div class="usr-head">
                        <div class="usr-avatar" aria-hidden="true">{{ $account->initials() }}</div>
                        <div class="usr-name">
                            <strong>{{ $account->name }}</strong>
                            <span>{{ $account->username ?: '-' }} &middot; {{ $account->email }}</span>
                        </div>
                    </div>

                    <div class="usr-badges">
                        <span class="usr-badge" style="--b: {{ $tone }}">{{ $isSuper ? 'Super admin' : 'Admin' }}</span>
                        <span class="usr-badge" style="--b: {{ $active ? '#4ade80' : '#94a3b8' }}">{{ $active ? 'Aktif' : 'Nonaktif' }}</span>
                        @if ($account->two_factor_confirmed_at)
                            <span class="usr-badge" style="--b: #22d3ee">2FA aktif</span>
                        @endif
                        @if ($isMe)
                            <span class="usr-badge" style="--b: #e879f9">Anda</span>
                        @endif
                        @if ($isSuper)
                            <span class="usr-badge" style="--b: #2dd4bf">Semua wilayah</span>
                        @else
                            @forelse ($account->wilayah as $area)
                                <span class="usr-badge" style="--b: #2dd4bf">{{ $area->name }}</span>
                            @empty
                                <span class="usr-badge" style="--b: #fb7185">Tanpa wilayah</span>
                            @endforelse
                        @endif
                    </div>

                    <div class="usr-actions">
                        <button type="button" class="rm-button"
                            data-usr-edit="{{ json_encode([
                                'id' => $account->id,
                                'name' => $account->name,
                                'username' => $account->username,
                                'email' => $account->email,
                                'role' => $account->role,
                                'wilayah_ids' => $account->wilayah->pluck('id')->values()->all(),
                            ]) }}">Ubah</button>

                        <form method="POST" action="{{ route('users.toggle', $account) }}"
                            onsubmit="return confirm('{{ $active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ e($account->name) }}?')">
                            @csrf
                            <button type="submit" class="rm-button {{ $active ? 'rm-danger' : '' }}" @disabled($isMe)>
                                {{ $active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>

                        @if (!$isSuper && !$isMe)
                            <form method="POST" action="{{ route('users.destroy', $account) }}"
                                onsubmit="return confirm({{ json_encode('Hapus akun '.$account->name.' secara permanen? Riwayat transaksi tetap tersimpan, tetapi akun tidak dapat dipulihkan.') }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rm-button rm-danger">Hapus</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    <dialog id="usr-dialog" class="router-modal" aria-labelledby="usr-title">
        <div class="router-modal-inner">
            <div class="router-modal-heading">
                <h2 id="usr-title">Tambah pengguna</h2>
                <button type="button" class="rm-close" data-close-dialog aria-label="Tutup">&times;</button>
            </div>

            <form id="usr-form" method="POST" action="{{ route('users.store') }}"
                data-store-url="{{ route('users.store') }}"
                data-update-url="{{ route('users.update', '__ID__') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="usr-method" disabled>
                <input type="hidden" name="_mode" id="usr-mode" value="create">
                <input type="hidden" name="_user_id" id="usr-id" value="">

                <label for="usr-name">Nama</label>
                <input id="usr-name" name="name" required maxlength="100" autocomplete="off">

                <div class="router-form-row usr-row">
                    <div>
                        <label for="usr-username">Username</label>
                        <input id="usr-username" name="username" required minlength="3" maxlength="50"
                            autocomplete="off" autocapitalize="none" spellcheck="false">
                    </div>
                    <div>
                        <label for="usr-role">Peran</label>
                        <select id="usr-role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super admin</option>
                        </select>
                    </div>
                </div>

                <fieldset id="usr-wilayah-box" style="border:1px solid rgba(148,163,184,.35);border-radius:10px;padding:.6rem .8rem;margin:.75rem 0">
                    <legend>Wilayah yang dikelola</legend>
                    @foreach ($wilayahList as $area)
                        <label style="display:flex;align-items:center;gap:.5rem;margin:.25rem 0">
                            <input type="checkbox" name="wilayah_ids[]" value="{{ $area->id }}" data-usr-wilayah style="width:auto;margin:0">
                            {{ $area->name }}
                        </label>
                    @endforeach
                    <p class="router-help">Admin hanya melihat dan mengelola data pada wilayah yang dicentang. Super admin otomatis mengakses semua wilayah.</p>
                </fieldset>

                <label for="usr-email">Email</label>
                <input id="usr-email" name="email" type="email" required maxlength="255"
                    autocomplete="off" autocapitalize="none" spellcheck="false">

                <label for="usr-password">Password <span id="usr-password-label"></span></label>
                <input id="usr-password" name="password" type="password" maxlength="1024"
                    autocomplete="new-password">
                <p class="router-help" id="usr-password-hint">
                    Minimal 10 karakter, mengandung huruf dan angka.
                </p>

                <div class="router-modal-actions">
                    <button class="rm-button" type="button" data-close-dialog>Batal</button>
                    <button class="router-save" type="submit">Simpan akun</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
    (function () {
        var dialog = document.getElementById('usr-dialog');
        var form = document.getElementById('usr-form');
        var el = function (id) { return document.getElementById(id); };

        function toggleWilayah() {
            var box = el('usr-wilayah-box');
            var isAdmin = el('usr-role').value === 'admin';
            box.hidden = !isAdmin;
            box.disabled = !isAdmin;
        }
        el('usr-role').addEventListener('change', toggleWilayah);

        function openDialog(mode, data) {
            var edit = mode === 'edit';
            form.action = edit
                ? form.dataset.updateUrl.replace('__ID__', encodeURIComponent(data.id))
                : form.dataset.storeUrl;
            el('usr-method').disabled = !edit;
            el('usr-mode').value = mode;
            el('usr-id').value = edit ? data.id : '';
            el('usr-title').textContent = edit ? 'Ubah pengguna' : 'Tambah pengguna';
            el('usr-name').value = data.name || '';
            el('usr-username').value = data.username || '';
            el('usr-email').value = data.email || '';
            el('usr-role').value = data.role || 'admin';
            var picked = (data.wilayah_ids || []).map(String);
            var boxes = dialog.querySelectorAll('[data-usr-wilayah]');
            boxes.forEach(function (b) { b.checked = picked.indexOf(b.value) !== -1; });
            if (!edit && !picked.length && boxes.length === 1) boxes[0].checked = true;
            toggleWilayah();
            el('usr-password').value = '';
            el('usr-password').required = !edit;
            el('usr-password-label').textContent = edit ? '(kosongkan jika tidak diganti)' : '';
            el('usr-password-hint').textContent = edit
                ? 'Mengganti password mengeluarkan akun ini dari sesi lain. Minimal 10 karakter, huruf dan angka.'
                : 'Minimal 10 karakter, mengandung huruf dan angka.';
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

        el('usr-add').addEventListener('click', function () { openDialog('create', {}); });

        document.querySelectorAll('[data-usr-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDialog('edit', JSON.parse(button.dataset.usrEdit));
            });
        });

        @if ($errors->any() && old('_mode'))
        @php
            $usrReopen = [
                'id' => old('_user_id'),
                'name' => old('name'),
                'username' => old('username'),
                'email' => old('email'),
                'role' => old('role'),
                'wilayah_ids' => old('wilayah_ids', []),
            ];
            $usrMode = old('_mode');
        @endphp
        openDialog(@json($usrMode), @json($usrReopen));
        @endif
    })();
    </script>

    @include('partials.billing-launcher')
    <script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
