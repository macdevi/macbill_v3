@php
    $screen = \App\Services\LoginScreen::forLogin();
    $businessName = $screen['name'];
    $slug = $screen['slug'];
    $fresh = ! $errors->any() && ! session('status');
@endphp
<!DOCTYPE html>
<html lang="id" style="--fg:{{ $screen['fg'] }};--dim:{{ $screen['dim'] }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#030503">
<title>Masuk | {{ $businessName }}</title>
<script>document.documentElement.classList.add('js');</script>
<style>
  :root{--bg:#0b0f0c;--fg:#33ff66;--dim:#1f9e40;--err:#ff5555;--warn:#ffcc33}
  *{box-sizing:border-box}
  html,body{margin:0;height:100%}
  body{background:#030503;display:flex;align-items:center;justify-content:center;font-family:"Courier New",Consolas,monospace;color:var(--fg);overflow:hidden}
  #rain{position:fixed;inset:0;z-index:0;opacity:.35}

  #splash{display:none;position:fixed;inset:0;z-index:5;flex-direction:column;align-items:center;justify-content:center;text-align:center;cursor:pointer;transition:opacity .6s,transform .6s;padding:20px}
  html.js.show-splash #splash{display:flex}
  #splash.out{opacity:0;transform:scale(1.15);pointer-events:none}
  #brand{font-size:clamp(34px,9vw,84px);font-weight:bold;letter-spacing:.08em;color:var(--fg);text-shadow:0 0 12px color-mix(in srgb,var(--fg) 80%,transparent),0 0 40px color-mix(in srgb,var(--fg) 40%,transparent);min-height:1.2em;overflow-wrap:anywhere}
  #brand.long{font-size:clamp(24px,6vw,52px)}
  #tag{margin-top:10px;font-size:clamp(12px,3vw,16px);color:var(--dim);min-height:1.5em;letter-spacing:.15em}
  #cta{margin-top:46px;font-size:clamp(13px,3.2vw,17px);color:var(--fg);opacity:0;transition:opacity .6s;animation:pulse 1.4s ease-in-out infinite}
  #cta.show{opacity:1}
  #load{margin-top:14px;min-height:1.5em;font-size:clamp(12px,3vw,15px);color:var(--dim);white-space:pre}
  @keyframes pulse{50%{text-shadow:0 0 14px var(--fg);transform:scale(1.05)}}
  .ring{position:absolute;width:60px;height:60px;border:2px solid var(--fg);border-radius:50%;pointer-events:none;transform:translate(-50%,-50%) scale(0);animation:ring .7s ease-out forwards}
  @keyframes ring{to{transform:translate(-50%,-50%) scale(6);opacity:0}}

  #term{display:none;position:relative;z-index:1;width:min(740px,94vw);background:rgba(11,15,12,.93);border:1px solid var(--dim);border-radius:8px;box-shadow:0 0 35px color-mix(in srgb,var(--fg) 20%,transparent);overflow:hidden}
  #term.on{display:block;animation:power .6s ease-out}
  #term::after{content:"";position:absolute;inset:0;pointer-events:none;background:repeating-linear-gradient(0deg,rgba(0,0,0,.18) 0 1px,transparent 1px 3px);animation:flick 5s infinite}
  .scan{position:absolute;left:0;right:0;height:60px;top:-60px;pointer-events:none;background:linear-gradient(transparent,color-mix(in srgb,var(--fg) 8%,transparent),transparent);animation:scan 6s linear infinite}
  @keyframes scan{to{top:100%}}
  @keyframes flick{0%,100%{opacity:1}50%{opacity:.93}52%{opacity:1}}
  @keyframes power{0%{transform:scaleY(.02) scaleX(.6);opacity:0}60%{transform:scaleY(.02) scaleX(1);opacity:1}100%{transform:none}}
  .bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#101a12;border-bottom:1px solid var(--dim);font-size:13px;color:var(--dim)}
  .dot{width:11px;height:11px;border-radius:50%;background:var(--dim);flex-shrink:0}
  #title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .body{padding:18px;height:min(430px,70vh);height:min(430px,70dvh);overflow-y:auto;font-size:15px;line-height:1.6}
  .line{white-space:pre-wrap;word-break:break-word;min-height:1.6em}
  .dim{color:var(--dim)} .err{color:var(--err)} .warn{color:var(--warn)}
  .row{display:flex;align-items:center;gap:8px}
  .row label{white-space:nowrap}
  input{flex:1;min-width:0;background:transparent;border:0;outline:0;color:var(--fg);font:inherit;font-size:16px;caret-color:var(--fg)}
  .caret::after{content:"";display:inline-block;width:9px;height:1em;background:var(--fg);margin-left:2px;vertical-align:text-bottom;animation:b 1s steps(1) infinite}
  @keyframes b{50%{opacity:0}}
  .shake{animation:shake .4s}
  @keyframes shake{20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
  .glitch{animation:glitch .35s}
  @keyframes glitch{0%{text-shadow:2px 0 #f0f,-2px 0 #0ff}50%{text-shadow:-3px 0 #f0f,3px 0 #0ff;transform:skewX(4deg)}100%{text-shadow:none}}
  .skip{display:none;position:fixed;right:12px;bottom:10px;z-index:2;font-size:12px;color:var(--dim);cursor:pointer;background:none;border:0;font-family:inherit;padding:8px}
  .skip.on{display:block}
  #realForm{display:none}
  .ns{display:none}
  html:not(.js) .ns{display:block;position:relative;z-index:2;width:min(420px,92vw);padding:20px;border:1px solid var(--dim);border-radius:8px;background:#0b0f0c}
  .ns label{display:block;margin:12px 0 4px}
  .ns input{width:100%;border:1px solid var(--dim);padding:10px;border-radius:6px}
  .ns button{margin-top:16px;width:100%;padding:10px;border:1px solid var(--fg);background:transparent;color:var(--fg);font:inherit;cursor:pointer}
  button:focus-visible{outline:2px solid var(--fg);outline-offset:3px}
  @media(prefers-reduced-motion:reduce){
    #rain{display:none}
    #term.on,#term::after,.scan,#cta,.shake,.glitch{animation:none}
    #splash{transition:none}
  }
</style>
</head>
<body>
<canvas id="rain" aria-hidden="true"></canvas>

<div id="splash" aria-hidden="true">
  <div id="brand"></div>
  <div id="tag"></div>
  <div id="cta">&gt; memuat halaman login...</div>
  <div id="load" aria-hidden="true"></div>
</div>

<div id="term">
  <div class="scan"></div>
  <div class="bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span id="title"></span></div>
  <div class="body" id="screen" role="log" aria-live="polite" aria-label="Terminal login"></div>
</div>
<button type="button" class="skip" id="skipBtn">[ lewati animasi ]</button>

<form id="realForm" method="POST" action="{{ route('login.store') }}">
  @csrf
  <input type="hidden" name="login" id="f_user">
  <input type="hidden" name="password" id="f_pass">
</form>

<noscript>
  <form class="ns" method="POST" action="{{ route('login.store') }}">
    @csrf
    <strong>{{ $businessName }}</strong>
    @if ($errors->any())
      <p class="err">@foreach ($errors->all() as $message){{ $message }} @endforeach</p>
    @endif
    <label for="ns-login">Username atau email</label>
    <input id="ns-login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required>
    <label for="ns-pass">Password</label>
    <input id="ns-pass" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Masuk</button>
  </form>
</noscript>

<script>
const BRAND = @json($businessName);
const SLUG = @json($slug);
const TAGLINE = @json($screen['tagline']);
const CMD = @json($screen['command']);
const TITLE = @json($screen['title']);
const MODULES = @json($screen['modules']);
const SPINNER = @json($screen['spinner']);
const MESSAGE = @json($screen['message']);
const SPLASH_ON = @json($screen['splash']);
const RAIN_ON = @json($screen['rain']);
const ERRORS = @json($errors->all());
const STATUS = @json(session('status'));
const OLD_LOGIN = @json(old('login', ''));
const FRESH = @json($fresh);

const root = document.documentElement;
const REDUCED = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
const SHOW_SPLASH = FRESH && !REDUCED && SPLASH_ON;
if (SHOW_SPLASH) root.classList.add('show-splash');

const screen = document.getElementById('screen');
const term = document.getElementById('term');
const splash = document.getElementById('splash');
const brandEl = document.getElementById('brand');
const tagEl = document.getElementById('tag');
const cta = document.getElementById('cta');
const loadEl = document.getElementById('load');
const skipBtn = document.getElementById('skipBtn');
document.getElementById('title').textContent = SLUG + '\u0040jaringan:~ login';
if (BRAND.length > 14) brandEl.classList.add('long');

let step = 0, user = '', pass = '';
let locked = true, skip = REDUCED, current = null;
let ready = false, entered = false;
const K = (ERRORS.length || STATUS) ? 0.35 : 1;
const sleep = ms => new Promise(r => setTimeout(r, skip ? 0 : ms * K));
const wait = ms => new Promise(r => setTimeout(r, ms));
const scrollDown = () => { screen.scrollTop = screen.scrollHeight; };

/* ---------- Splash ---------- */
async function intro() {
  const pool = '01#$%&*<>/\\';
  for (let i = 0; i <= BRAND.length; i++) {
    for (let k = 0; k < 3; k++) {
      let t = BRAND.slice(0, i);
      if (i < BRAND.length) t += pool[Math.floor(Math.random() * pool.length)];
      brandEl.textContent = t;
      await wait(35);
    }
    brandEl.textContent = BRAND.slice(0, i);
  }
  brandEl.classList.add('glitch');
  await wait(300);
  for (const ch of TAGLINE) { tagEl.textContent += ch; await wait(22); }
  await wait(300);
  cta.classList.add('show');
  ready = true;
  const total = 20;
  for (let i = 0; i <= total && !entered; i++) {
    loadEl.textContent = '[' + '#'.repeat(i) + '.'.repeat(total - i) + '] ' + (i * 5) + '%';
    await wait(60);
  }
  await wait(250);
  if (!entered) enter({});
}

function enter(ev) {
  if (!ready || entered) return;
  entered = true;
  const r = document.createElement('div');
  r.className = 'ring';
  r.style.left = (ev.clientX || innerWidth / 2) + 'px';
  r.style.top = (ev.clientY || innerHeight / 2) + 'px';
  splash.appendChild(r);
  brandEl.classList.remove('glitch');
  void brandEl.offsetWidth;
  brandEl.classList.add('glitch');
  setTimeout(() => {
    splash.classList.add('out');
    openTerminal();
  }, 450);
  setTimeout(() => splash.remove(), 1200);
}
splash.addEventListener('click', enter);
addEventListener('keydown', e => {
  if (!entered && ready && SHOW_SPLASH && (e.key === 'Enter' || e.key === ' ')) {
    e.preventDefault();
    enter({});
  }
});

function openTerminal() {
  entered = true;
  term.classList.add('on');
  if (!skip) skipBtn.classList.add('on');
  boot();
}

/* ---------- Terminal ---------- */
function line(cls = '') {
  const d = document.createElement('div');
  d.className = 'line ' + cls;
  screen.appendChild(d);
  scrollDown();
  return d;
}
async function type(text, cls = '', speed = 18) {
  const d = line(cls);
  if (skip) { d.textContent = text; return d; }
  d.classList.add('caret');
  for (const ch of text) {
    d.textContent += ch;
    scrollDown();
    await sleep(speed + Math.random() * 20);
  }
  d.classList.remove('caret');
  return d;
}
async function progress(label, ms = 700) {
  const d = line('dim');
  const W = 24, steps = 24;
  for (let i = 0; i <= steps; i++) {
    const f = Math.round(i / steps * W);
    d.textContent = label.padEnd(26, '.') + ' [' + '#'.repeat(f) + '-'.repeat(W - f) + '] ' + String(Math.round(i / steps * 100)).padStart(3) + '%';
    await sleep(ms / steps);
  }
  d.textContent = label.padEnd(26, '.') + ' [ OK ]';
}
async function spinner(label, ms = 900) {
  const d = line('dim');
  const fr = ['|', '/', '-', '\\'];
  const t0 = Date.now();
  let i = 0;
  while (Date.now() - t0 < (skip ? 0 : ms)) {
    d.textContent = fr[i++ % 4] + ' ' + label;
    await sleep(90);
  }
  d.textContent = '* ' + label + ' selesai';
}

async function boot() {
  await type(CMD, 'dim', 30);
  await sleep(200);
  await type(TITLE);
  await type(TAGLINE, 'dim', 10);
  await sleep(250);
  for (const m of MODULES) { await progress(m, 650); }
  await spinner(SPINNER);
  line();
  if (STATUS) { await type(STATUS, 'warn', 12); line(); }
  if (ERRORS.length) {
    term.classList.remove('shake');
    void term.offsetWidth;
    term.classList.add('shake');
    for (const m of ERRORS) {
      const e = await type('ACCESS DENIED: ' + m, 'err', 12);
      e.classList.add('glitch');
    }
    line();
  } else {
    await type(MESSAGE, 'warn', 14);
    line();
  }
  skipBtn.classList.remove('on');
  if (matchMedia('(pointer: coarse)').matches) {
    await type('Ketuk layar untuk mulai mengetik.', 'dim', 10);
  }
  step = 0;
  prompt('login: ', false, OLD_LOGIN);
  locked = false;
}

function prompt(label, secret, value = '') {
  const row = document.createElement('div');
  row.className = 'row';
  const lab = document.createElement('label');
  lab.textContent = label;
  const inp = document.createElement('input');
  inp.type = secret ? 'password' : 'text';
  inp.autocomplete = secret ? 'current-password' : (step === 0 ? 'username' : 'off');
  inp.autocapitalize = 'off';
  inp.spellcheck = false;
  inp.value = value;
  inp.setAttribute('aria-label', step === 0 ? 'Username atau email' : (secret ? 'Password' : 'Ingat saya, ketik y atau n'));
  row.append(lab, inp);
  screen.appendChild(row);
  scrollDown();
  inp.focus();
  current = { row, inp, secret, label, caps: null };

  inp.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
  if (secret) {
    const check = e => {
      const on = e.getModifierState && e.getModifierState('CapsLock');
      if (on && !current.caps) {
        current.caps = line('warn');
        current.caps.textContent = 'WARN: Caps Lock sedang aktif.';
      } else if (!on && current.caps) {
        current.caps.remove();
        current.caps = null;
      }
    };
    inp.addEventListener('keydown', check);
    inp.addEventListener('keyup', check);
  }
}
function focusInput() { if (current && !locked) current.inp.focus(); }
term.addEventListener('click', focusInput);

function freeze() {
  const { row, inp, secret, label, caps } = current;
  if (caps) caps.remove();
  const d = document.createElement('div');
  d.className = 'line';
  d.textContent = label + (secret ? '' : inp.value);
  row.replaceWith(d);
  const v = inp.value;
  current = null;
  return v;
}

async function submit() {
  if (locked || !current) return;
  if (step < 2 && !current.inp.value.trim()) return;
  const v = freeze();

  if (step === 0) { user = v.trim(); step = 1; prompt('password: ', true); return; }
  pass = v;
  locked = true;

  const d = line('dim');
  const fr = ['.  ', '.. ', '...'];
  for (let i = 0; i < 9; i++) { d.textContent = 'Memverifikasi' + fr[i % 3]; await wait(100); }

  document.getElementById('f_user').value = user;
  document.getElementById('f_pass').value = pass;
  document.getElementById('realForm').submit();
}

skipBtn.addEventListener('click', () => { skip = true; });
addEventListener('keydown', () => { if (locked && entered) skip = true; });
addEventListener('pageshow', e => { if (e.persisted) location.reload(); });

/* ---------- Latar hujan karakter ---------- */
const cv = document.getElementById('rain');
const cx = cv.getContext('2d');
const RAIN_COLOR = getComputedStyle(root).getPropertyValue('--dim').trim() || '#1f9e40';
if (!RAIN_ON) cv.style.display = 'none';
let cols, drops, rainTimer = null;
const FS = 16;
function resize() {
  cv.width = innerWidth;
  cv.height = innerHeight;
  cols = Math.floor(cv.width / FS);
  drops = Array(cols).fill(0).map(() => Math.random() * -40);
}
addEventListener('resize', resize);
resize();
const chars = '01ABCDEF{}[]<>/\\$#%PPPoE';
function drawRain() {
  cx.fillStyle = 'rgba(3,5,3,.12)';
  cx.fillRect(0, 0, cv.width, cv.height);
  cx.fillStyle = RAIN_COLOR;
  cx.font = FS + 'px monospace';
  for (let i = 0; i < cols; i++) {
    cx.fillText(chars[Math.floor(Math.random() * chars.length)], i * FS, drops[i] * FS);
    if (drops[i] * FS > cv.height && Math.random() > .975) drops[i] = 0;
    drops[i]++;
  }
}
function startRain() { if (!REDUCED && RAIN_ON && !rainTimer) rainTimer = setInterval(drawRain, 60); }
function stopRain() { clearInterval(rainTimer); rainTimer = null; }
document.addEventListener('visibilitychange', () => { document.hidden ? stopRain() : startRain(); });
startRain();

if (SHOW_SPLASH) { intro(); } else { splash.remove(); openTerminal(); }
</script>
</body>
</html>
