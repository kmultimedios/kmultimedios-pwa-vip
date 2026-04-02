<?php
/**
 * Vader App – KMultimedios VIP
 * Gate de acceso + App en un solo archivo
 */

// ── Cargar WordPress ─────────────────────────────────────────────────
$wp_load = dirname(__FILE__) . '/../wp-load.php';
if (!file_exists($wp_load)) $wp_load = dirname(__FILE__) . '/../../wp-load.php';
if (!file_exists($wp_load)) die('Error: no se encontró WordPress.');
require_once($wp_load);

// ── Niveles VIP permitidos ───────────────────────────────────────────
$vip_levels = [4, 5, 8, 9, 10, 11, 12];

// ── ¿Está logueado? ──────────────────────────────────────────────────
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/vader/')));
    exit;
}

// ── ¿Tiene membresía VIP? ────────────────────────────────────────────
if (!function_exists('pmpro_hasMembershipLevel') || !pmpro_hasMembershipLevel($vip_levels)) {
    wp_redirect(home_url('/membresias/'));
    exit;
}

// ── Usuario VIP confirmado ───────────────────────────────────────────
$user      = wp_get_current_user();
$user_name = $user->display_name ?: $user->user_login;
$level     = pmpro_getMembershipLevelForUser($user->ID);
$level_name = $level ? $level->name : 'VIP';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<meta name="mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
<meta name="apple-mobile-web-app-title" content="Vader"/>
<meta name="theme-color" content="#06000f"/>
<title>Vader – KMultimedios VIP</title>
<link rel="manifest" href="/vader/manifest.json"/>
<link rel="apple-touch-icon" href="/vader/icons/icon-192.png"/>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Orbitron:wght@700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
<style>
:root {
  --p:#b026ff; --pg:rgba(176,38,255,.45); --pd:#8b1fc7;
  --g:#00ff41; --gg:rgba(0,255,65,.4);
  --c:#00d4ff; --cg:rgba(0,212,255,.4);
  --r:#ff3355; --rg:rgba(255,51,85,.4);
  --bg:#06000f; --s1:#0f0520; --s2:#1a0a30;
  --t:#e8d5ff; --td:#9070b8;
  --gold:#ffd700;
  --fd:'Orbitron',monospace; --fu:'Rajdhani',sans-serif; --fm:'Share Tech Mono',monospace;
  --rad:.875rem; --tr:300ms cubic-bezier(.4,0,.2,1);
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{font-family:var(--fu);background:var(--bg);color:var(--t);min-height:100vh;overflow-x:hidden}

.bg{position:fixed;inset:0;z-index:0;pointer-events:none}
.bg-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(176,38,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(176,38,255,.06) 1px,transparent 1px);background-size:48px 48px}
.bg-glow{position:absolute;top:-200px;left:50%;transform:translateX(-50%);width:700px;height:500px;background:radial-gradient(ellipse,rgba(176,38,255,.18) 0%,transparent 70%)}
.scanline{position:absolute;width:100%;height:2px;background:linear-gradient(transparent,rgba(176,38,255,.3),transparent);animation:scan 6s linear infinite}
@keyframes scan{0%{top:0}100%{top:100%}}

/* Header */
.hdr{position:sticky;top:0;z-index:100;background:rgba(6,0,15,.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(176,38,255,.3);padding:.6rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.hdr-l{display:flex;align-items:center;gap:.75rem}
.hdr-logo{height:30px;filter:drop-shadow(0 0 8px var(--pg))}
.hdr-title{font-family:var(--fd);font-size:.85rem;font-weight:900;color:var(--p);letter-spacing:.06em}
.vip-badge{background:linear-gradient(135deg,var(--gold),#ffb300);color:#000;font-family:var(--fm);font-size:.58rem;font-weight:700;padding:.12rem .45rem;border-radius:99px;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.hdr-r{display:flex;align-items:center;gap:.6rem}
.hdr-user{font-family:var(--fm);font-size:.72rem;color:var(--td);display:flex;align-items:center;gap:.4rem}
.hdr-user i{color:var(--p)}
.logout{background:transparent;border:1px solid rgba(176,38,255,.3);color:var(--td);padding:.28rem .6rem;border-radius:.4rem;font-family:var(--fm);font-size:.68rem;cursor:pointer;text-decoration:none;transition:all var(--tr)}
.logout:hover{border-color:var(--p);color:var(--p)}

/* Main */
.main{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:1.25rem 1rem 3rem}

/* Section header */
.sh{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.7rem 1rem;background:var(--s1);border:1px solid rgba(176,38,255,.25);border-left:3px solid var(--p);border-radius:var(--rad)}
.sh i{color:var(--p);font-size:1rem;width:1.2rem;text-align:center}
.sh h2{font-family:var(--fd);font-size:.82rem;font-weight:700;color:var(--p);text-transform:uppercase;letter-spacing:.04em}
.sh p{font-family:var(--fm);font-size:.7rem;color:var(--td)}
.live{margin-left:auto;display:flex;align-items:center;gap:.4rem;font-family:var(--fm);font-size:.65rem;color:var(--r)}
.live span{width:6px;height:6px;border-radius:50%;background:var(--r);box-shadow:0 0 6px var(--rg);animation:bl 1.5s infinite}
@keyframes bl{0%,100%{opacity:1}50%{opacity:.3}}
.sec{margin-bottom:1.75rem}

/* Video */
.vid-wrap{position:relative;width:100%;padding-bottom:56.25%;background:#000;border:1px solid rgba(176,38,255,.35);border-radius:var(--rad);overflow:hidden;box-shadow:0 0 25px rgba(176,38,255,.12)}
.vid-wrap video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}

/* Camera grid */
.cam-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
.cam-card{background:var(--s1);border:1px solid rgba(176,38,255,.25);border-radius:var(--rad);overflow:hidden;transition:all var(--tr)}
.cam-card:hover{border-color:var(--p);box-shadow:0 0 18px rgba(176,38,255,.2)}
.cam-vid{position:relative;width:100%;padding-bottom:56.25%;background:#000}
.cam-vid video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.cam-lbl{padding:.55rem .8rem;display:flex;align-items:center;justify-content:space-between}
.cam-name{font-family:var(--fu);font-size:.88rem;font-weight:700}
.cam-st{font-family:var(--fm);font-size:.65rem;color:var(--g);display:flex;align-items:center;gap:.3rem}
.cam-st::before{content:'';display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--g);box-shadow:0 0 5px var(--gg);animation:bl 2s infinite}

/* Radio */
.radio-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
.rc{background:var(--s1);border:1px solid rgba(0,212,255,.2);border-radius:var(--rad);padding:.9rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;transition:all var(--tr)}
.rc:hover{border-color:var(--c);box-shadow:0 0 14px var(--cg)}
.rc.playing{border-color:var(--r);box-shadow:0 0 14px var(--rg)}
.rc-logo{width:50px;height:50px;border-radius:.45rem;object-fit:contain;background:#fff;padding:.3rem;flex-shrink:0}
.rc-info{flex:1;min-width:0}
.rc-name{font-family:var(--fu);font-size:.95rem;font-weight:700;color:var(--c)}
.rc-genre{font-family:var(--fm);font-size:.68rem;color:var(--td)}
.rc-song{font-family:var(--fm);font-size:.7rem;color:var(--t);margin-top:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pb{width:38px;height:38px;border-radius:50%;border:none;background:var(--c);color:#000;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all var(--tr);box-shadow:0 0 10px var(--cg)}
.pb:hover{transform:scale(1.1)}
.pb.playing{background:var(--r);box-shadow:0 0 10px var(--rg)}

/* Community */
.comm{background:linear-gradient(135deg,var(--s2),var(--s1));border:1px solid rgba(176,38,255,.35);border-radius:var(--rad);padding:1.5rem;text-align:center;position:relative;overflow:hidden}
.comm::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--p),transparent);animation:scan2 3s linear infinite}
@keyframes scan2{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
.comm h3{font-family:var(--fd);font-size:.9rem;color:var(--p);margin-bottom:.4rem;text-transform:uppercase}
.comm p{font-family:var(--fm);font-size:.78rem;color:var(--td);margin-bottom:1rem}
.btn-p{display:inline-flex;align-items:center;gap:.5rem;background:var(--p);color:#fff;padding:.6rem 1.5rem;border-radius:99px;text-decoration:none;font-family:var(--fu);font-size:.95rem;font-weight:700;transition:all var(--tr);box-shadow:0 0 14px var(--pg)}
.btn-p:hover{background:var(--pd);transform:translateY(-2px);box-shadow:0 4px 20px var(--pg)}

/* Footer */
.ftr{position:relative;z-index:1;text-align:center;padding:1rem;border-top:1px solid rgba(176,38,255,.15);font-family:var(--fm);font-size:.7rem;color:var(--td)}
.ftr a{color:var(--p);text-decoration:none}

@media(max-width:600px){
  .main{padding:1rem .75rem 2.5rem}
  .cam-grid,.radio-grid{grid-template-columns:1fr}
  .hdr-user span{display:none}
}
</style>
</head>
<body>
<div class="bg"><div class="bg-grid"></div><div class="bg-glow"></div><div class="scanline"></div></div>

<header class="hdr">
  <div class="hdr-l">
    <img src="https://kmultimedios.com/logosk/kmultimedios.png" alt="KM" class="hdr-logo" onerror="this.style.display='none'">
    <div class="hdr-title">VADER</div>
    <span class="vip-badge"><?php echo esc_html($level_name); ?></span>
  </div>
  <div class="hdr-r">
    <div class="hdr-user">
      <i class="fas fa-user-shield"></i>
      <span><?php echo esc_html($user_name); ?></span>
    </div>
    <a href="<?php echo wp_logout_url(home_url('/camaras/')); ?>" class="logout">
      <i class="fas fa-sign-out-alt"></i> Salir
    </a>
  </div>
</header>

<main class="main">

  <!-- Cámaras VIP -->
  <section class="sec">
    <div class="sh">
      <i class="fas fa-video"></i>
      <div><h2>Cámaras Fronterizas VIP</h2><p>Acceso exclusivo en tiempo real</p></div>
      <div class="live"><span></span>EN VIVO</div>
    </div>
    <div class="cam-grid">
      <div class="cam-card">
        <div class="cam-vid"><video id="cam1" controls playsinline muted autoplay></video></div>
        <div class="cam-lbl"><span class="cam-name">Douglas / Agua Prieta</span><span class="cam-st">En vivo</span></div>
      </div>
      <div class="cam-card">
        <div class="cam-vid"><video id="cam2" controls playsinline muted autoplay></video></div>
        <div class="cam-lbl"><span class="cam-name">Nogales</span><span class="cam-st">En vivo</span></div>
      </div>
    </div>
  </section>

  <!-- NvisorTV -->
  <section class="sec">
    <div class="sh">
      <i class="fas fa-broadcast-tower"></i>
      <div><h2>NvisorTV</h2><p>Canal principal 24/7</p></div>
      <div class="live"><span></span>EN VIVO</div>
    </div>
    <div class="vid-wrap">
      <video id="nvisor" controls playsinline muted autoplay></video>
    </div>
  </section>

  <!-- Radios -->
  <section class="sec">
    <div class="sh">
      <i class="fas fa-radio"></i>
      <div><h2>3 Radios en Vivo</h2><p>Transmisión 24/7</p></div>
    </div>
    <div class="radio-grid">
      <div class="rc" onclick="toggleRadio('kreactivo',this)">
        <img src="https://kmultimedios.com/logosk/kreactivo.png" class="rc-logo" onerror="this.src='https://via.placeholder.com/50/00d4ff/000?text=KR'">
        <div class="rc-info">
          <div class="rc-name">KREACTIVO</div>
          <div class="rc-genre">Retro Mix</div>
          <div class="rc-song" id="song-kreactivo">Conectando...</div>
        </div>
        <button class="pb"><i class="fas fa-play"></i></button>
      </div>
      <div class="rc" onclick="toggleRadio('ladelrancho',this)">
        <img src="https://kmultimedios.com/logosk/ladelrancho.png" class="rc-logo" onerror="this.src='https://via.placeholder.com/50/00d4ff/000?text=LR'">
        <div class="rc-info">
          <div class="rc-name">LA DEL RANCHO</div>
          <div class="rc-genre">Regional Mexicana</div>
          <div class="rc-song" id="song-ladelrancho">Conectando...</div>
        </div>
        <button class="pb"><i class="fas fa-play"></i></button>
      </div>
      <div class="rc" onclick="toggleRadio('kdelamor',this)">
        <img src="https://kmultimedios.com/logosk/kdelamor.png" class="rc-logo" onerror="this.src='https://via.placeholder.com/50/00d4ff/000?text=KA'">
        <div class="rc-info">
          <div class="rc-name">K DEL AMOR</div>
          <div class="rc-genre">Romántica</div>
          <div class="rc-song" id="song-kdelamor">Conectando...</div>
        </div>
        <button class="pb"><i class="fas fa-play"></i></button>
      </div>
    </div>
  </section>

  <!-- Comunidad -->
  <section class="sec">
    <div class="comm">
      <h3>Comunidad KMultimedios</h3>
      <p>Comenta, comparte y conecta con otros miembros en el foro.</p>
      <a href="https://foro.kmultimedios.com" class="btn-p" target="_blank" rel="noopener">
        <i class="fas fa-comments"></i> Entrar al Foro
      </a>
    </div>
  </section>

</main>

<footer class="ftr">
  <p>© <?php echo date('Y'); ?> <a href="https://kmultimedios.com">KMultimedios LLC</a> · Acceso VIP exclusivo</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
// ── URLs de streams (agrega tus URLs aquí) ───────────────────────────
var STREAMS = {
  nvisor: 'https://nx1.serverse.com/hls/kmc.m3u8',
  cam1:   '',  // ← URL HLS cámara Douglas/Agua Prieta
  cam2:   ''   // ← URL HLS cámara Nogales
};

var RADIOS = {
  kreactivo:   'https://stream.kreactivo.com/kreactivo',
  ladelrancho: 'https://stream.kreactivo.com/ladelrancho',
  kdelamor:    'https://stream.kreactivo.com/kdelamor'
};

var META = {
  kreactivo:   'https://stream.kreactivo.com/status-json.xsl?mount=/kreactivo',
  ladelrancho: 'https://stream.kreactivo.com/status-json.xsl?mount=/ladelrancho',
  kdelamor:    'https://stream.kreactivo.com/status-json.xsl?mount=/kdelamor'
};

// ── HLS ──────────────────────────────────────────────────────────────
function initHLS(id, url) {
  if (!url) return;
  var v = document.getElementById(id);
  if (!v) return;
  if (Hls.isSupported()) {
    var h = new Hls({lowLatencyMode:true});
    h.loadSource(url); h.attachMedia(v);
  } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
    v.src = url;
  }
}

// ── Radio ─────────────────────────────────────────────────────────────
var audios = {}, playing = null;
function toggleRadio(st, card) {
  var btn = card.querySelector('.pb');
  if (playing === st) {
    audios[st].pause();
    btn.innerHTML = '<i class="fas fa-play"></i>';
    btn.classList.remove('playing'); card.classList.remove('playing');
    playing = null; return;
  }
  if (playing) {
    audios[playing].pause();
    document.querySelectorAll('.rc').forEach(function(c){
      c.classList.remove('playing');
      c.querySelector('.pb').innerHTML='<i class="fas fa-play"></i>';
      c.querySelector('.pb').classList.remove('playing');
    });
  }
  if (!audios[st]) { audios[st] = new Audio(RADIOS[st]); audios[st].volume = .75; }
  audios[st].play();
  btn.innerHTML = '<i class="fas fa-pause"></i>';
  btn.classList.add('playing'); card.classList.add('playing');
  playing = st;
}

function fetchMeta(st) {
  fetch(META[st]).then(function(r){return r.json();}).then(function(d){
    var src = d.icestats && d.icestats.source;
    var el = document.getElementById('song-'+st);
    if (src && el) el.textContent = src.title || src.yp_currently_playing || '';
  }).catch(function(){});
}

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  initHLS('nvisor', STREAMS.nvisor);
  initHLS('cam1',   STREAMS.cam1);
  initHLS('cam2',   STREAMS.cam2);
  ['kreactivo','ladelrancho','kdelamor'].forEach(function(s){
    fetchMeta(s);
    setInterval(function(){ fetchMeta(s); }, 15000);
  });
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/vader/sw.js').catch(function(){});
}
</script>
</body>
</html>
