<section class="tf-card"
    id="router-live-panel"
    data-endpoint="{{ route('routers.monitor') }}"
    aria-labelledby="mikrotik-title">
    <div class="tf-top">
        <h2 class="tf-title" id="mikrotik-title">
            Trafik Jaringan<span class="mt-router-name" data-monitor="router_name" hidden style="display:none"></span>
        </h2>
        <span class="mikrotik-api-status" data-state="unknown">
            <i aria-hidden="true"></i><span data-monitor="status">Memeriksa…</span>
        </span>
    </div>

    <div class="tf-traffic">
        <div class="tf-rate">
            <div class="tf-big"><strong data-monitor="rx">&mdash;</strong><small>Mbps</small></div>
            <div class="tf-sub">&darr; Download</div>
        </div>
        <div class="tf-rate">
            <div class="tf-big"><strong data-monitor="tx">&mdash;</strong><small>Mbps</small></div>
            <div class="tf-sub">&uarr; Upload</div>
        </div>
        <span data-monitor="wan" hidden style="display:none">&mdash;</span>
    </div>

    <svg class="tf-chart" viewBox="0 0 400 45" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="tf-rx-grad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#75ccff" stop-opacity=".42"/>
                <stop offset="1" stop-color="#75ccff" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="tf-tx-grad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#ff6b87" stop-opacity=".38"/>
                <stop offset="1" stop-color="#ff6b87" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <path id="tf-rx-area" d="" fill="url(#tf-rx-grad)" stroke="none"/>
        <path id="tf-tx-area" d="" fill="url(#tf-tx-grad)" stroke="none"/>
        <path id="tf-rx-line" d="" fill="none" stroke="#75ccff" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
        <path id="tf-tx-line" d="" fill="none" stroke="#ff6b87" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
    </svg>

    <div class="tf-info">
        <span data-monitor="identity">&mdash;</span>
        &middot; CPU <span data-monitor="cpu">&mdash;</span>
        &middot; Uptime <span data-monitor="uptime">&mdash;</span>
    </div>

    <div class="tf-foot">
        <span data-monitor="note">Pembaruan otomatis setiap 3 detik</span>
        <span class="tf-live" id="tf-live"><i aria-hidden="true"></i><span id="tf-age">&mdash;</span></span>
        @can('manage-system')
            <a href="{{ route('routers.index') }}">Pengaturan &rarr;</a>
        @endcan
    </div>
</section>

<style>
#router-live-panel.tf-card{position:relative;overflow:hidden;box-sizing:border-box;min-width:0;padding:20px;border-radius:20px;border:1px solid #ffffff20;background:linear-gradient(125deg,#153a70,#154b8a 50%,#0a2344);box-shadow:0 12px 28px rgba(0,0,0,.2);color:#e8eff8}
#router-live-panel .tf-top{display:flex;justify-content:space-between;align-items:center;gap:12px}
#router-live-panel .tf-title{margin:0;color:inherit;font-size:12px;font-weight:650;letter-spacing:1.8px;line-height:1.4;text-transform:uppercase}
#router-live-panel .mikrotik-api-status{display:inline-flex;align-items:center;gap:6px;margin:0;padding:6px 9px;border:0;border-radius:20px;background:#ffffff14;color:#b5c9e0;font-size:10px;font-weight:500;letter-spacing:1px;line-height:1.2;text-transform:uppercase;white-space:nowrap}
#router-live-panel .mikrotik-api-status i{display:inline-block;flex-shrink:0;width:6px;height:6px;margin:0;border-radius:50%;background:#8da0b9}
#router-live-panel .mikrotik-api-status[data-state="connected"]{background:#34d39920;color:#83efc5}
#router-live-panel .mikrotik-api-status[data-state="connected"] i{background:#4ee2aa}
#router-live-panel .mikrotik-api-status[data-state="disconnected"]{background:#f8717122;color:#fca5a5}
#router-live-panel .mikrotik-api-status[data-state="disconnected"] i{background:#f87171}
#router-live-panel .tf-traffic{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px 28px;margin-top:16px}
#router-live-panel .tf-rate{min-width:0}
#router-live-panel .tf-big{display:flex;align-items:baseline;gap:4px;line-height:1.1}
#router-live-panel .tf-big strong{color:#fff;font-size:30px;font-weight:700;font-variant-numeric:tabular-nums}
#router-live-panel .tf-big small{color:#b5c9e0;font-size:12px}
#router-live-panel .tf-sub{margin-top:4px;color:#b5c9e0;font-size:12px}
#router-live-panel .tf-chart{display:block;width:100%;height:45px;margin-top:12px}
#router-live-panel .tf-info{margin-top:10px;color:#b5c9e0;font-size:12px;line-height:1.5;overflow-wrap:anywhere}
#router-live-panel .tf-info span{color:#eaf3ff;font-weight:600}
#router-live-panel .tf-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:8px;color:#8da7c5;font-size:10px;line-height:1.5}
#router-live-panel .tf-foot span{min-width:0;overflow-wrap:anywhere}
#router-live-panel .tf-foot a{flex-shrink:0;color:#9cd3ff;text-decoration:none}
#router-live-panel .tf-foot a:hover{text-decoration:underline}
@media(max-width:600px){#router-live-panel.tf-card{padding:16px;border-radius:16px}#router-live-panel .tf-traffic{gap:10px 22px;margin-top:14px}#router-live-panel .tf-big strong{font-size:26px}}
#router-live-panel #tf-rx-line{filter:drop-shadow(0 0 4px rgba(117,204,255,.55))}
#router-live-panel #tf-tx-line{filter:drop-shadow(0 0 4px rgba(255,107,135,.50))}
/* Trafik: latar hijau preview 03 */
#router-live-panel.tf-card{background:linear-gradient(130deg,#083d43,#0c2635)}
/* Trafik: hijau lebih gelap */
#router-live-panel.tf-card{background:linear-gradient(130deg,#05292e,#07141d)}
/* Trafik: angka ringkas dan grafik lega */
#router-live-panel .tf-big strong{font-size:21px}
#router-live-panel .tf-big::before{align-self:center;margin-right:2px;font-size:15px;font-weight:700;line-height:1}
#router-live-panel .tf-rate:first-child .tf-big::before{content:"\2193";color:#75ccff}
#router-live-panel .tf-rate:nth-child(2) .tf-big::before{content:"\2191";color:#ff6b87}
#router-live-panel .tf-sub{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
#router-live-panel .tf-traffic{margin-top:12px}
#router-live-panel .tf-chart{height:56px;margin-top:10px}
@media(max-width:600px){#router-live-panel .tf-big strong{font-size:18px}#router-live-panel .tf-chart{height:50px}}
#router-live-panel .tf-foot{flex-wrap:wrap}
#router-live-panel .tf-live{display:inline-flex;align-items:center;gap:5px;flex-shrink:0;color:#9fb8d4}
#router-live-panel .tf-live i{display:inline-block;width:6px;height:6px;border-radius:50%;background:#4ee2aa;animation:tf-pulse 1s ease-in-out infinite}
#router-live-panel .tf-live[data-stale] i{background:#8da0b9;animation:none}
@keyframes tf-pulse{50%{opacity:.3}}
@media(prefers-reduced-motion:reduce){#router-live-panel .tf-live i{animation:none}}
</style>
