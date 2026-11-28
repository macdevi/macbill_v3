@php
    $launcherItems = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'match' => 'dashboard',
            'gate' => null,
            'icon' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        ],
        [
            'label' => 'Pelanggan',
            'route' => 'customers.index',
            'match' => 'customers.*',
            'gate' => 'access-billing',
            'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
        ],
        [
            'label' => 'Paket Internet',
            'route' => 'packages.index',
            'match' => 'packages.*',
            'gate' => 'manage-system',
            'icon' => 'm12 3 9 5-9 5-9-5 9-5z M3 8v9l9 5 9-5V8 M12 13v9 M7.5 5.5l9 5',
        ],
        [
            'label' => 'Tagihan',
            'route' => 'invoices.index',
            'match' => 'invoices.*',
            'gate' => 'access-billing',
            'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z M14 2v6h6 M8 13h8 M8 17h5',
        ],
        [
            'label' => 'Pengeluaran',
            'route' => 'expenses.index',
            'match' => 'expenses.*',
            'gate' => 'access-billing',
            'icon' => 'M20 8V6a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h14v10H6a3 3 0 0 1-3-3V7 M20 13h-5a2 2 0 0 0 0 4h5 M7 15h4',
        ],
        [
            'label' => 'Pasang Baru',
            'route' => 'pasang-baru.create',
            'match' => 'pasang-baru.*',
            'gate' => 'access-billing',
            'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M19 8v6 M22 11h-6',
        ],
        [
            'label' => 'Titip Saldo',
            'route' => 'balances.index',
            'match' => 'balances.*',
            'gate' => 'access-billing',
            'icon' => 'M12 2v20 M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
        ],
        [
            'label' => 'Router MikroTik',
            'route' => 'routers.index',
            'match' => 'routers.*',
            'gate' => 'manage-system',
            'icon' => 'M7 11V4 M17 11V4 M6 11h12a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-3a3 3 0 0 1 3-3z M7 16h.01 M11 16h.01 M17 16h.01',
        ],
        [
            'label' => 'Pengguna',
            'route' => 'users.index',
            'match' => 'users.*',
            'gate' => 'manage-users',
            'icon' => 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2',
        ],
        [
            'label' => 'Wilayah',
            'route' => 'wilayah.index',
            'match' => 'wilayah.*',
            'gate' => 'manage-users',
            'icon' => 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z M12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
        ],
        [
            'label' => 'Pengaturan',
            'route' => 'billing-settings.index',
            'match' => 'billing-settings.*',
            'gate' => 'manage-users',
            'icon' => 'M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M10 15v6',
        ],
        [
            'label' => 'Pengaturan Umum',
            'route' => 'business-profile.edit',
            'match' => 'business-profile.*',
            'gate' => 'manage-users',
            'icon' => 'M3 21h18 M5 21V7l8-4v18 M19 21V11l-6-4 M9 9v.01 M9 12v.01 M9 15v.01 M9 18v.01',
        ],
    ];
@endphp

@php
    $toneMap = [
        'Dashboard' => '#60a5fa',
        'Pelanggan' => '#22d3ee',
        'Paket Internet' => '#a78bfa',
        'Tagihan' => '#fbbf24',
        'Pengeluaran' => '#fb7185',
        'Titip Saldo' => '#4ade80',
        'Pasang Baru' => '#34d399',
        'Router MikroTik' => '#fb923c',
        'Pengguna' => '#e879f9',
        'Pengaturan' => '#94a3b8',
        'Pengaturan Umum' => '#818cf8',
        'Wilayah' => '#2dd4bf',
    ];
    $launcherItems = array_map(
        fn ($entry) => $entry + ['tone' => $toneMap[$entry['label']] ?? '#94a3b8'],
        $launcherItems
    );
@endphp

@php
    $barLabels = ['Dashboard', 'Pelanggan', 'Tagihan', 'Pengeluaran', 'Pasang Baru', 'Titip Saldo'];
    $barItems = collect($launcherItems)->filter(function ($item) use ($barLabels) {
        return in_array($item['label'], $barLabels, true)
            && (!$item['gate'] || auth()->user()->can($item['gate']));
    })->sortBy(fn ($item) => array_search($item['label'], $barLabels))->values();
@endphp

<nav class="billing-bottom-nav" aria-label="Menu bawah">
    @foreach($barItems as $item)
        @php
            $barReady = $item['route'] && \Illuminate\Support\Facades\Route::has($item['route']);
            $barActive = $barReady && request()->routeIs($item['match']);
        @endphp

        @if($barReady)
            <a class="billing-bottom-item {{ $barActive ? 'is-active' : '' }}"
                style="--tone: {{ $item['tone'] }}"
                href="{{ route($item['route']) }}"
                @if($barActive) aria-current="page" @endif>
        @else
            <button type="button" class="billing-bottom-item is-pending" style="--tone: {{ $item['tone'] }}"
                title="Halaman belum tersedia" disabled>
        @endif
            <svg width="22" height="22" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.6"
                stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">
                <path d="{{ $item['icon'] }}"/>
            </svg>
            <span>{{ $item['label'] }}</span>
        @if($barReady)
            </a>
        @else
            </button>
        @endif
    @endforeach
</nav>

<nav class="billing-desktop-nav" aria-label="Menu utama desktop">
    <div class="billing-desktop-links">
        @foreach($launcherItems as $desktopItem)
            @continue($desktopItem['gate'] && !auth()->user()->can($desktopItem['gate']))
            @php
                $desktopReady = $desktopItem['route']
                    && \Illuminate\Support\Facades\Route::has($desktopItem['route']);
                $desktopActive = $desktopReady
                    && request()->routeIs($desktopItem['match']);
            @endphp

            @if($desktopReady)
                <a class="billing-desktop-item {{ $desktopActive ? 'is-active' : '' }}"
                    style="--tone: {{ $desktopItem['tone'] }}"
                    href="{{ route($desktopItem['route']) }}"
                    @if($desktopActive) aria-current="page" @endif>
            @else
                <button type="button"
                    class="billing-desktop-item is-pending" style="--tone: {{ $desktopItem['tone'] }}"
                    title="Halaman belum tersedia" disabled>
            @endif
                <svg width="21" height="21" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="{{ $desktopItem['icon'] }}"/>
                </svg>
                <span>{{ $desktopItem['label'] }}</span>
            @if($desktopReady)
                </a>
            @else
                </button>
            @endif
        @endforeach
    </div>

    <div class="billing-desktop-account">
        <div class="billing-desktop-user">
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ auth()->user()->role === 'super_admin' ? 'Super admin' : 'Admin' }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="billing-desktop-logout">
                <svg width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.7"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M9 5H5v14h4 M10 12h11 M17 8l4 4-4 4"/>
                </svg>
                <span>Keluar</span>
            </button>
        </form>
    </div>
</nav>

<script>
(function () {
    var nav = document.querySelector('.billing-desktop-nav');
    if (!nav) return;

    function updateNavigationSpace() {
        var height = nav.getBoundingClientRect().height;
        document.body.style.setProperty(
            '--billing-desktop-nav-space',
            Math.ceil(height + 20) + 'px'
        );
    }

    updateNavigationSpace();
    window.addEventListener('resize', updateNavigationSpace);
    window.addEventListener('load', updateNavigationSpace);

    if ('ResizeObserver' in window) {
        new ResizeObserver(updateNavigationSpace).observe(nav);
    }
})();
</script>

<dialog id="billing-launcher" class="billing-launcher"
    aria-labelledby="billing-launcher-title"
    aria-describedby="billing-launcher-description">
    <div class="billing-launcher-panel">
        <div class="billing-launcher-heading">
            <div>
                <h2 id="billing-launcher-title">Menu aplikasi</h2>
                <p id="billing-launcher-description">Tagihan dan Management Jaringan</p>
            </div>
            <button type="button" class="billing-launcher-close"
                data-launcher-close aria-label="Tutup menu">
                <svg width="19" height="19" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.7"
                    stroke-linecap="round" aria-hidden="true">
                    <path d="m6 6 12 12 M18 6 6 18"/>
                </svg>
            </button>
        </div>

        <nav class="billing-launcher-grid" aria-label="Menu utama">
            @foreach($launcherItems as $item)
                @continue($item['gate'] && !auth()->user()->can($item['gate']))
                @php
                    $ready = $item['route'] && \Illuminate\Support\Facades\Route::has($item['route']);
                    $active = $ready && request()->routeIs($item['match']);
                @endphp

                @if($ready)
                    <a class="billing-launcher-item {{ $active ? 'is-active' : '' }}"
                        style="--tone: {{ $item['tone'] }}"
                        href="{{ route($item['route']) }}"
                        @if($active) aria-current="page" @endif>
                @else
                    <button type="button" class="billing-launcher-item is-pending" style="--tone: {{ $item['tone'] }}" disabled>
                @endif
                    <span class="billing-launcher-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.55"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <path d="{{ $item['icon'] }}"/>
                        </svg>
                    </span>
                    <span class="billing-launcher-label">{{ $item['label'] }}</span>
                    <span class="billing-launcher-caption">
                        {{ !$ready ? 'Belum siap' : ($active ? 'Halaman ini' : 'Buka halaman') }}
                    </span>
                @if($ready)
                    </a>
                @else
                    </button>
                @endif
            @endforeach
        </nav>

        
        <section class="billing-launcher-user" aria-label="Akun pengguna">
            <div class="billing-launcher-user-avatar" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/>
                </svg>
            </div>

            <div class="billing-launcher-user-info">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->role === 'super_admin' ? 'Super admin' : 'Admin' }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}"
                class="billing-launcher-logout-form">
                @csrf
                <button type="submit" class="billing-launcher-logout">
                    <svg width="17" height="17" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M9 5H5v14h4 M10 12h11 M17 8l4 4-4 4"/>
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </section>

        <div class="billing-launcher-footer">Pilih menu untuk berpindah halaman.</div>
    </div>
</dialog>
