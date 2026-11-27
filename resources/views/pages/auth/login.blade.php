<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#091321">
    <title>Masuk | Tagihan dan Management Jaringan</title>
    <style>
        :root{color-scheme:dark;--bg:#091321;--surface:#101f32;--line:#26374b;--text:#edf4fc;--muted:#a4b5ca;--cyan:#64e3f1}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        button,input{font:inherit}
        a,button,input{-webkit-tap-highlight-color:transparent}
        button{cursor:pointer}
        .page{min-height:100svh;display:grid;grid-template-columns:1.1fr 1fr}
        .visual{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:48px 60px;background:radial-gradient(ellipse at 45% 42%,#143b51 0%,#0b1a2c 48%,#091321 90%);border-right:1px solid var(--line)}
        .visual::before{content:"";position:absolute;inset:0;z-index:-1;background-image:linear-gradient(#b5dfff06 1px,transparent 1px),linear-gradient(90deg,#b5dfff06 1px,transparent 1px);background-size:48px 48px}
        .brand{display:flex;align-items:center;gap:12px}
        .logo{display:grid;place-items:center;flex-shrink:0;width:44px;height:44px;border:1px solid #64e3f140;border-radius:14px;background:#64e3f110;color:var(--cyan)}
        .brand strong{font-size:15px;line-height:1.5;display:block;letter-spacing:.1px}
        .brand small{display:block;color:var(--muted);font-size:12px;margin-top:2px}
        .hero{position:relative;max-width:560px;padding:48px 0 30px}
        .eyebrow{font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;color:var(--cyan)}
        h1{font-size:clamp(36px,3.7vw,56px);line-height:1.12;letter-spacing:-2px;font-weight:650;margin:20px 0}
        h1 span{color:var(--cyan)}
        .hero p{max-width:420px;font-size:15px;line-height:1.8;color:var(--muted)}
        .network{display:block;width:100%;max-width:460px;height:auto;margin:12px auto 0;overflow:visible}
        .network .wire{fill:none;stroke:#64e3f13d;stroke-width:1.5}
        .network .flow{fill:none;stroke:var(--cyan);stroke-width:2;stroke-dasharray:6 120;animation:flow 9s linear infinite}
        .network .node{fill:#112b40;stroke:#64e3f170;stroke-width:1.5}
        .network .core{fill:#123c50;stroke:var(--cyan);stroke-width:1.5}
        .network .halo{fill:none;stroke:#64e3f13b;transform-box:fill-box;transform-origin:center;animation:pulse 4s ease-out infinite}
        .visual-footer{color:#8296ae;font-size:12px;line-height:1.6}
        .main{display:flex;align-items:center;justify-content:center;padding:48px 36px;background:radial-gradient(ellipse at top right,#12243860,transparent 65%)}
        .panel{width:100%;max-width:410px}
        .mobile-brand{display:none;margin-bottom:44px}
        .badge{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);padding:7px 11px;border-radius:100px;color:#b8cadc;font-size:11px;letter-spacing:.5px}
        .badge svg{color:var(--cyan)}
        h2{font-size:32px;font-weight:650;letter-spacing:-1px;margin:22px 0 10px}
        .intro{color:var(--muted);font-size:14px;line-height:1.7;margin:0 0 30px}
        .field{margin-bottom:20px}
        label{display:block;font-size:13px;font-weight:600;color:#dce6f2;margin-bottom:9px}
        .input-wrap{position:relative}
        .input-icon{position:absolute;left:15px;top:16px;color:#8096b1;pointer-events:none}
        input[type=email],input[type=password],input[type=text]{width:100%;min-height:52px;background:#101f32;border:1px solid #2a3a50;border-radius:12px;padding:13px 15px 13px 44px;color:var(--text);outline:none;transition:border-color .18s,box-shadow .18s}
        input::placeholder{color:#7488a1}
        input:focus{border-color:var(--cyan);box-shadow:0 0 0 3px #64e3f115}
        input[aria-invalid=true]{border-color:#ff9ba5}
        #password{padding-right:76px}
        .toggle{position:absolute;right:5px;top:5px;min-height:42px;padding:0 10px;background:transparent;border:0;color:#b5cadf;font-size:12px;border-radius:8px}
        .toggle:hover{background:#ffffff08;color:white}
        .options{margin:4px 0 24px}
        .remember{display:inline-flex;align-items:center;gap:10px;min-height:32px;margin:0;font-weight:400;color:var(--muted);cursor:pointer}
        .remember input{width:17px;height:17px;margin:0;accent-color:var(--cyan)}
        .submit{width:100%;min-height:52px;border:1px solid #9af3fa;border-radius:12px;background:var(--cyan);color:#08212b;font-weight:750;display:flex;align-items:center;justify-content:center;gap:10px;transition:background .18s,transform .18s}
        .submit:hover{background:#99f0f8;transform:translateY(-1px)}
        .submit:active{transform:translateY(0)}
        .submit:disabled{opacity:.7;cursor:wait;transform:none}
        button:focus-visible,a:focus-visible{outline:2px solid var(--cyan);outline-offset:4px}
        .notice{margin:25px 0 0;padding-top:22px;border-top:1px solid var(--line);font-size:12px;line-height:1.8;color:#91a6bd}
        .notice strong{color:#c3d0df;font-weight:500}
        .error,.status{padding:12px 14px;font-size:13px;line-height:1.6;border-radius:10px;margin-bottom:20px}
        .error{border:1px solid #ff9ba540;background:#ff9ba50b;color:#ffc4cb}
        .status{border:1px solid #64e3f140;background:#64e3f10b;color:#a5f1f7}
        .footer{margin-top:28px;color:#72869e;font-size:11px;letter-spacing:.2px}
        @keyframes flow{to{stroke-dashoffset:-252}}
        @keyframes pulse{0%,100%{opacity:.3;transform:scale(.95)}50%{opacity:.8;transform:scale(1.08)}}
        @media(min-width:1500px){.visual{padding:60px 7vw}}
        @media(max-width:900px){.visual{padding:36px}.hero h1{font-size:40px}.main{padding:36px 28px}}
        @media(max-width:700px){
            .page{grid-template-columns:1fr}
            .visual{display:none}
            .main{padding:36px 24px;min-height:100svh}
            .mobile-brand{display:flex}
            .panel{max-width:420px}
            h2{font-size:30px}
        }
        @media(max-width:360px){.main{padding:28px 18px}.brand strong{font-size:13px}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
    </style>
<link id="billing-theme-css" rel="stylesheet" href="{{ asset('css/billing-theme.css') }}?v=1795252022983102024">
<script src="{{ asset('js/billing-theme.js') }}?v=1795252022983102024"></script>
</head>
<body class="billing-login">
    <div class="page">
        <aside class="visual" aria-label="Tentang aplikasi">
            <div class="brand">
                <div class="logo" aria-hidden="true">
                    <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <rect x="8" y="2" width="8" height="6" rx="1.5"/>
                        <path d="M12 8v5M4 17v-4h16v4"/>
                        <rect x="1" y="17" width="6" height="5" rx="1"/>
                        <rect x="9" y="17" width="6" height="5" rx="1"/>
                        <rect x="17" y="17" width="6" height="5" rx="1"/>
                        <path d="M12 13v4"/>
                    </svg>
                </div>
                <div>
                    <strong>Tagihan dan<br>Management Jaringan</strong>
                    <small>Ruang kerja pengelola RT/RW Net</small>
                </div>
            </div>

            <div class="hero">
                <div class="eyebrow">Billing & pengelolaan layanan</div>
                <h1>Jaringan tertata.<br><span>Tagihan terkelola.</span></h1>
                <p>Satu ruang kerja untuk mengelola pelanggan, layanan internet, dan pencatatan pembayaran.</p>

                <svg class="network" viewBox="0 0 460 220" aria-hidden="true">
                    <path class="wire" d="M80 65L230 115L380 55M230 115L370 180M230 115L90 180M230 115V25"/>
                    <path class="flow" d="M80 65L230 115L380 55"/>
                    <path class="flow" d="M370 180L230 115L90 180" style="animation-delay:-4s"/>
                    <circle class="halo" cx="230" cy="115" r="47"/>
                    <circle class="core" cx="230" cy="115" r="30"/>
                    <g fill="none" stroke="#a0f4fb" stroke-width="2">
                        <rect x="215" y="105" width="30" height="20" rx="5"/>
                        <path d="M222 105V96M238 105V96M222 115h3M231 115h3M239 115h1"/>
                    </g>
                    <circle class="node" cx="80" cy="65" r="12"/>
                    <circle class="node" cx="380" cy="55" r="12"/>
                    <circle class="node" cx="90" cy="180" r="12"/>
                    <circle class="node" cx="370" cy="180" r="12"/>
                    <circle class="node" cx="230" cy="25" r="9"/>
                    <g fill="#91e9f2">
                        <circle cx="80" cy="65" r="3"/>
                        <circle cx="380" cy="55" r="3"/>
                        <circle cx="90" cy="180" r="3"/>
                        <circle cx="370" cy="180" r="3"/>
                        <circle cx="230" cy="25" r="2"/>
                    </g>
                </svg>
            </div>

            <div class="visual-footer">Dibangun untuk pekerjaan harian pengelola jaringan.</div>
        </aside>

        <main class="main">
            <section class="panel" aria-labelledby="login-title">
                <div class="brand mobile-brand">
                    <div class="logo" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="8" y="2" width="8" height="6" rx="1"/>
                            <path d="M12 8v5M4 17v-4h16v4M12 13v4"/>
                            <rect x="1" y="17" width="6" height="5" rx="1"/>
                            <rect x="9" y="17" width="6" height="5" rx="1"/>
                            <rect x="17" y="17" width="6" height="5" rx="1"/>
                        </svg>
                    </div>
                    <div>
                        <strong>Tagihan dan<br>Management Jaringan</strong>
                    </div>
                </div>

                <div class="badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 3l8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4z"/>
                        <path d="M8 12l3 3 5-6"/>
                    </svg>
                    PORTAL PENGELOLA
                </div>

                <h2 id="login-title">Selamat datang kembali</h2>
                <p class="intro">Masuk untuk membuka ruang kerja Anda.</p>

                @if (session('status'))
                    <div class="status" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="error" id="login-errors" role="alert">
                        @foreach ($errors->all() as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" id="login-form">
                    @csrf

                    <div class="field">
                        <label for="login">Username atau email</label>
                        <div class="input-wrap">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="3"/>
                                <path d="M3 7l9 6 9-6"/>
                            </svg>
                            <input id="login" name="login" type="text"
                                value="{{ old('login') }}"
                                placeholder="Username atau alamat email"
                                autocomplete="username" required autofocus
                                @if ($errors->any()) aria-describedby="login-errors" @endif
                                @error('login') aria-invalid="true" @enderror>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="3"/>
                                <path d="M8 10V7a4 4 0 018 0v3M12 14v3"/>
                            </svg>
                            <input id="password" name="password" type="password"
                                placeholder="Masukkan password"
                                autocomplete="current-password" required
                                @error('password') aria-invalid="true" aria-describedby="login-errors" @enderror>
                            <button type="button" class="toggle" id="toggle-password"
                                aria-controls="password" aria-pressed="false"
                                aria-label="Tampilkan password" hidden>Lihat</button>
                        </div>
                    </div>

                    <div class="options">
                        <label class="remember" for="remember">
                            <input id="remember" name="remember" type="checkbox"
                                value="1" @checked(old('remember'))>
                            Ingat saya di perangkat ini
                        </label>
                    </div>

                    <button class="submit" type="submit" data-test="login-button">
                        <span id="submit-label">Masuk ke dashboard</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                </form>

                <p class="notice">
                    <strong>Akses khusus pengelola.</strong><br>
                    Gunakan akun yang diberikan oleh pengelola aplikasi.
                    Hindari opsi ingat saya pada perangkat bersama.
                </p>
                <div class="footer">&copy; {{ date('Y') }} Tagihan dan Management Jaringan</div>
            </section>
        </main>
    </div>

    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');
        const form = document.getElementById('login-form');
        const submit = form.querySelector('button[type="submit"]');
        const submitLabel = document.getElementById('submit-label');

        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            toggle.textContent = visible ? 'Tutup' : 'Lihat';
            toggle.setAttribute('aria-pressed', String(visible));
            toggle.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
        });

        form.addEventListener('submit', () => {
            submit.disabled = true;
            submitLabel.textContent = 'Memeriksa akun...';
            form.setAttribute('aria-busy', 'true');
        });

        window.addEventListener('pageshow', () => {
            submit.disabled = false;
            submitLabel.textContent = 'Masuk ke dashboard';
            form.removeAttribute('aria-busy');
        });
    </script>
</body>
</html>
