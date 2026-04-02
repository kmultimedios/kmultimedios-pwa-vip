<?php
/**
 * Template Name: Vader – Instalar App
 * Version: 1.0 - Página pública de instalación PWA
 *
 * @package KMultimedios
 * @since 1.0
 */

// ============================================
// SESSION DATA (página pública — sin check VIP)
// ============================================
$current_user = wp_get_current_user();

$wm_email = ($current_user && !empty($current_user->user_email))
    ? $current_user->user_email
    : 'visitante';

$wm_ip_raw = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';

if (strpos($wm_ip_raw, ',') !== false) {
    $wm_ip_raw = trim(explode(',', $wm_ip_raw)[0]);
}

$wm_ip   = $wm_ip_raw;
$wm_uid  = get_current_user_id();
$wm_user = $current_user->display_name ?: $current_user->user_login ?: 'Visitante';
$wm_time = wp_date('Y-m-d H:i:s');

$wm_level = '';
if (function_exists('pmpro_getMembershipLevelForUser') && $wm_uid) {
    $lvl = pmpro_getMembershipLevelForUser($wm_uid);
    if ($lvl) $wm_level = strtoupper($lvl->name);
}

$is_vip = false;
if (function_exists('pmpro_hasMembershipLevel')) {
    $is_vip = pmpro_hasMembershipLevel([4, 5, 8, 9, 10, 11, 12]);
}

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Instalar Vader App – KMultimedios</title>
<meta name="description" content="Instala la app Vader de KMultimedios en tu dispositivo iOS, Android o Windows."/>
<meta name="robots" content="index, follow"/>
<link rel="canonical" href="<?php echo esc_url(home_url('/instalar-vader/')); ?>"/>
<?php wp_head(); ?>
<style>
/* Ocultar elementos del tema que se inyectan en páginas custom */
body.vader-install-page .site-header,
body.vader-install-page #masthead,
body.vader-install-page .main-navigation,
body.vader-install-page .navbar,
body.vader-install-page header.entry-header,
body.vader-install-page .km-header,
body.vader-install-page .top-bar,
body.vader-install-page .header-wrap,
body.vader-install-page #header { display:none !important; }
body.vader-install-page { padding-top:0 !important; margin-top:0 !important; }
</style>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&family=Orbitron:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous"/>
<style>
:root {
  --purple:       #b026ff;
  --purple-dark:  #8b1fc7;
  --purple-dim:   #2d0a5a;
  --purple-glow:  rgba(176,38,255,0.45);
  --green:        #00ff41;
  --green-glow:   rgba(0,255,65,0.4);
  --cyan:         #00d4ff;
  --cyan-glow:    rgba(0,212,255,0.4);
  --bg:           #06000f;
  --surface:      #0f0520;
  --surface2:     #1a0a30;
  --text:         #e8d5ff;
  --text-dim:     #9070b8;
  --ios-color:    #a8b8c8;
  --android-color:#3ddc84;
  --windows-color:#00adef;
  --font-display: 'Orbitron',monospace;
  --font-ui:      'Rajdhani',sans-serif;
  --font-mono:    'Share Tech Mono',monospace;
  --radius:       1rem;
  --transition:   300ms cubic-bezier(0.4,0,0.2,1);
}
* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body.vader-install-page { font-family:var(--font-ui); background:var(--bg); color:var(--text); min-height:100vh; overflow-x:hidden; line-height:1.5; }
body.vader-install-page * { font-family:inherit; }

.bg-layer { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
.bg-grid { position:absolute; inset:0; background-image:linear-gradient(rgba(176,38,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(176,38,255,.07) 1px,transparent 1px); background-size:50px 50px; }
.bg-glow { position:absolute; top:-200px; left:50%; transform:translateX(-50%); width:800px; height:600px; background:radial-gradient(ellipse at center,rgba(176,38,255,.2) 0%,transparent 70%); }

.vi-header { position:relative; z-index:10; display:flex; justify-content:space-between; align-items:center; padding:1rem 2rem; border-bottom:1px solid rgba(176,38,255,.3); background:#06000f; }
.vi-logo { display:flex; align-items:center; gap:.75rem; text-decoration:none; }
.vi-logo img { height:36px !important; width:auto !important; max-width:160px !important; display:block !important; object-fit:contain; filter:drop-shadow(0 0 8px var(--purple-glow)); }
.vi-logo span { font-family:var(--font-display); font-size:1rem; font-weight:700; color:var(--purple); letter-spacing:.05em; }
.vi-back { font-family:var(--font-mono); font-size:.78rem; color:var(--text-dim); text-decoration:none; display:flex; align-items:center; gap:.4rem; transition:color var(--transition); }
.vi-back:hover { color:var(--purple); }

/* Badge VIP si está logueado */
.vi-user-bar { background:rgba(176,38,255,.08); border-bottom:1px solid rgba(176,38,255,.15); padding:.4rem 2rem; display:flex; align-items:center; gap:.75rem; font-family:var(--font-mono); font-size:.72rem; color:var(--text-dim); position:relative; z-index:9; }
.vi-user-bar i { color:var(--purple); }
.vi-vip-badge { background:linear-gradient(135deg,#ffd700,#ffb300); color:#000; font-size:.6rem; font-weight:700; padding:.12rem .45rem; border-radius:99px; text-transform:uppercase; letter-spacing:.05em; }

.vi-hero { position:relative; z-index:1; text-align:center; padding:4rem 1.5rem 3rem; }
.vi-icon { font-size:5rem; margin-bottom:1.5rem; filter:drop-shadow(0 0 20px var(--purple-glow)); animation:float 3s ease-in-out infinite; display:block; }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
.vi-hero h1 { font-family:var(--font-display); font-size:clamp(2rem,6vw,3.5rem); font-weight:900; background:linear-gradient(135deg,var(--purple),var(--cyan)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-bottom:1rem; letter-spacing:.04em; text-transform:uppercase; }
.vi-hero-sub { font-family:var(--font-mono); font-size:1rem; color:var(--text-dim); max-width:480px; margin:0 auto 2.5rem; letter-spacing:.04em; line-height:1.6; }
.vi-badge { display:inline-flex; align-items:center; gap:.5rem; background:rgba(176,38,255,.15); border:1px solid var(--purple); border-radius:99px; padding:.4rem 1rem; font-family:var(--font-mono); font-size:.75rem; color:var(--purple); letter-spacing:.08em; }
.vi-badge span { width:7px; height:7px; border-radius:50%; background:var(--green); box-shadow:0 0 8px var(--green-glow); animation:blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

.vi-section { position:relative; z-index:1; max-width:900px; margin:0 auto; padding:0 1.5rem 1.5rem; }
.vi-label { font-family:var(--font-mono); font-size:.75rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.15em; text-align:center; margin-bottom:1.25rem; }
.vi-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:2.5rem; }
.vi-card { background:var(--surface); border:2px solid rgba(176,38,255,.25); border-radius:var(--radius); padding:1.75rem 1rem; text-align:center; cursor:pointer; transition:all var(--transition); position:relative; overflow:hidden; }
.vi-card::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,var(--card-color,var(--purple)),transparent); opacity:0; transition:opacity var(--transition); }
.vi-card:hover { border-color:var(--card-color,var(--purple)); transform:translateY(-4px); box-shadow:0 8px 30px rgba(176,38,255,.2); }
.vi-card.active { border-color:var(--card-color,var(--purple)); background:var(--surface2); box-shadow:0 0 30px var(--card-glow,var(--purple-glow)); }
.vi-card.active::before { opacity:.08; }
.c-ios     { --card-color:var(--ios-color);     --card-glow:rgba(168,184,200,.4); }
.c-android { --card-color:var(--android-color); --card-glow:rgba(61,220,132,.4); }
.c-windows { --card-color:var(--windows-color); --card-glow:rgba(0,173,239,.4); }
.vi-card-icon { font-size:2.8rem; margin-bottom:.75rem; display:block; }
.c-ios     .vi-card-icon { color:var(--ios-color); }
.c-android .vi-card-icon { color:var(--android-color); }
.c-windows .vi-card-icon { color:var(--windows-color); }
.vi-card-name { font-family:var(--font-display); font-size:.85rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
.c-ios     .vi-card-name { color:var(--ios-color); }
.c-android .vi-card-name { color:var(--android-color); }
.c-windows .vi-card-name { color:var(--windows-color); }
.vi-card-hint { font-family:var(--font-mono); font-size:.7rem; color:var(--text-dim); margin-top:.35rem; }
.vi-detected { position:absolute; top:.5rem; right:.5rem; background:var(--green); color:#000; font-family:var(--font-mono); font-size:.6rem; font-weight:700; padding:.15rem .45rem; border-radius:99px; text-transform:uppercase; display:none; }
.vi-card.detected .vi-detected { display:block; }

.vi-panel { display:none; background:var(--surface); border:2px solid rgba(176,38,255,.3); border-radius:var(--radius); padding:2rem; margin-bottom:2rem; animation:slideIn .3s ease; }
.vi-panel.visible { display:block; }
@keyframes slideIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.vi-panel-head { display:flex; align-items:center; gap:1rem; margin-bottom:1.75rem; padding-bottom:1.25rem; border-bottom:1px solid rgba(176,38,255,.2); }
.vi-panel-icon { font-size:2rem; }
.vi-panel-title { font-family:var(--font-display); font-size:1.2rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.vi-panel-sub { font-family:var(--font-mono); font-size:.78rem; color:var(--text-dim); margin-top:.2rem; }

.vi-steps { display:flex; flex-direction:column; gap:1rem; }
.vi-step { display:flex; gap:1rem; align-items:flex-start; padding:1rem 1.25rem; background:rgba(176,38,255,.05); border:1px solid rgba(176,38,255,.15); border-radius:.75rem; transition:all var(--transition); }
.vi-step:hover { background:rgba(176,38,255,.1); border-color:rgba(176,38,255,.35); }
.vi-step-num { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:.9rem; font-weight:900; flex-shrink:0; }
.p-ios     .vi-step-num { background:rgba(168,184,200,.15); color:var(--ios-color);     border:2px solid var(--ios-color); }
.p-android .vi-step-num { background:rgba(61,220,132,.15);  color:var(--android-color); border:2px solid var(--android-color); }
.p-windows .vi-step-num { background:rgba(0,173,239,.15);   color:var(--windows-color); border:2px solid var(--windows-color); }
.vi-step-body { flex:1; }
.vi-step-title { font-family:var(--font-ui); font-size:1.05rem; font-weight:700; color:var(--text); margin-bottom:.25rem; }
.vi-step-desc  { font-family:var(--font-mono); font-size:.82rem; color:var(--text-dim); line-height:1.55; }
.vi-step-icon  { font-size:1.4rem; flex-shrink:0; align-self:center; }

.vi-install-btn { width:100%; margin-top:1.5rem; padding:1rem; border-radius:var(--radius); font-family:var(--font-display); font-size:.95rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.75rem; transition:all var(--transition); text-transform:uppercase; letter-spacing:.05em; border:2px solid; }
.vi-install-btn:hover { transform:translateY(-2px); }
.vi-install-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }
.vi-btn-android { background:linear-gradient(135deg,#1a4a2e,#0d2a1a); border-color:var(--android-color); color:var(--android-color); }
.vi-btn-android:hover { box-shadow:0 0 25px rgba(61,220,132,.4); }
.vi-btn-windows { background:linear-gradient(135deg,#0a2a3a,#051a2a); border-color:var(--windows-color); color:var(--windows-color); }
.vi-btn-windows:hover { box-shadow:0 0 25px rgba(0,173,239,.4); }

.vi-note { margin-top:1.25rem; padding:.75rem 1rem; background:rgba(0,255,65,.06); border-left:3px solid var(--green); border-radius:0 .5rem .5rem 0; font-family:var(--font-mono); font-size:.78rem; color:var(--text-dim); line-height:1.6; }
.vi-note strong { color:var(--green); }

.vi-cta { position:relative; z-index:1; max-width:700px; margin:0 auto; padding:1rem 1.5rem 4rem; text-align:center; }
.vi-cta-card { background:linear-gradient(135deg,var(--purple-dim),var(--surface2)); border:2px solid var(--purple); border-radius:var(--radius); padding:2.5rem; position:relative; overflow:hidden; }
.vi-cta-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--purple),transparent); animation:scan 3s linear infinite; }
@keyframes scan { 0%{transform:translateX(-100%)} 100%{transform:translateX(100%)} }
.vi-cta-card h2 { font-family:var(--font-display); font-size:1.4rem; font-weight:800; color:var(--purple); margin-bottom:.75rem; text-transform:uppercase; }
.vi-cta-card p { font-family:var(--font-mono); font-size:.88rem; color:var(--text-dim); margin-bottom:1.75rem; line-height:1.6; }
.vi-cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.vi-btn-prim { background:var(--purple); color:#fff; padding:.8rem 1.75rem; border-radius:99px; text-decoration:none; font-family:var(--font-ui); font-size:1rem; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; transition:all var(--transition); box-shadow:0 0 20px var(--purple-glow); }
.vi-btn-prim:hover { background:var(--purple-dark); transform:translateY(-2px); }
.vi-btn-out { background:transparent; color:var(--cyan); padding:.8rem 1.75rem; border:2px solid var(--cyan); border-radius:99px; text-decoration:none; font-family:var(--font-ui); font-size:1rem; font-weight:700; display:inline-flex; align-items:center; gap:.5rem; transition:all var(--transition); }
.vi-btn-out:hover { background:var(--cyan); color:#000; transform:translateY(-2px); }

.vi-footer { position:relative; z-index:1; text-align:center; padding:1.5rem; border-top:1px solid rgba(176,38,255,.2); font-family:var(--font-mono); font-size:.75rem; color:var(--text-dim); }
.vi-footer a { color:var(--purple); text-decoration:none; }

@media(max-width:600px){
  .vi-cards { gap:.6rem; }
  .vi-card { padding:1.25rem .5rem; }
  .vi-card-icon { font-size:2rem; }
  .vi-card-name { font-size:.7rem; }
  .vi-card-hint { display:none; }
  .vi-header { padding:.75rem 1rem; }
  .vi-hero { padding:2.5rem 1rem 2rem; }
  .vi-panel { padding:1.25rem; }
  .vi-step { padding:.75rem 1rem; }
  .vi-user-bar { padding:.4rem 1rem; }
}
</style>
</head>
<body class="vader-install-page">

<div class="bg-layer"><div class="bg-grid"></div><div class="bg-glow"></div></div>

<!-- Header -->
<header class="vi-header">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="vi-logo">
        <img src="https://kmultimedios.com/logosk/kmultimedios.png" alt="KMultimedios" onerror="this.style.display='none'">
        <span>KMULTIMEDIOS</span>
    </a>
    <a href="https://foro.kmultimedios.com" class="vi-back">
        <i class="fas fa-arrow-left"></i> Volver al Foro
    </a>
</header>

<?php if (is_user_logged_in()): ?>
<div class="vi-user-bar">
    <i class="fas fa-user-shield"></i>
    <span><?php echo esc_html($wm_user); ?></span>
    <?php if ($wm_level): ?>
        <span class="vi-vip-badge"><?php echo esc_html($wm_level); ?></span>
    <?php endif; ?>
    <?php if ($is_vip): ?>
        <span style="margin-left:auto;color:var(--green);font-size:.68rem;">
            <i class="fas fa-check-circle"></i> Membresía activa
        </span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Hero -->
<section class="vi-hero">
    <span class="vi-icon">🛡️</span>
    <h1>Instala Vader App</h1>
    <p class="vi-hero-sub">Accede a las cámaras fronterizas en tiempo real desde tu pantalla de inicio — sin tiendas de apps.</p>
    <div class="vi-badge"><span></span> Disponible para iOS · Android · Windows</div>
</section>

<!-- Plataformas -->
<section class="vi-section">
    <p class="vi-label">Selecciona tu dispositivo</p>

    <div class="vi-cards">
        <div class="vi-card c-ios" id="card-ios" onclick="selectPlatform('ios')">
            <span class="vi-detected">Tu dispositivo</span>
            <span class="vi-card-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
            </span>
            <div class="vi-card-name">iPhone / iPad</div>
            <div class="vi-card-hint">iOS 14+</div>
        </div>
        <div class="vi-card c-android" id="card-android" onclick="selectPlatform('android')">
            <span class="vi-detected">Tu dispositivo</span>
            <span class="vi-card-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M17.523 15.341c-.41 0-.744-.334-.744-.744V9.743c0-.41.334-.744.744-.744.41 0 .744.334.744.744v4.854c0 .41-.334.744-.744.744m-11.046 0c-.41 0-.744-.334-.744-.744V9.743c0-.41.334-.744.744-.744.41 0 .744.334.744.744v4.854c0 .41-.334.744-.744.744M7.604 6.08l-.864-1.582A.16.16 0 0 1 6.796 4.3a.16.16 0 0 1 .218.058l.876 1.605a5.86 5.86 0 0 1 2.11-.391 5.86 5.86 0 0 1 2.11.391l.876-1.605A.16.16 0 0 1 13.204 4.3a.16.16 0 0 1 .056.198l-.864 1.582C13.666 6.7 14.5 7.636 14.5 8.7H9.5c0-1.064.834-2 1.604-2.62M9.904 7.6a.496.496 0 1 0 0-.992.496.496 0 0 0 0 .992m4.192 0a.496.496 0 1 0 0-.992.496.496 0 0 0 0 .992M6.5 9.2h11v8.4a1.4 1.4 0 0 1-1.4 1.4h-.6v2.256a.744.744 0 1 1-1.488 0V19h-2.024v2.256a.744.744 0 1 1-1.488 0V19h-.6A1.4 1.4 0 0 1 6.5 17.6z"/></svg>
            </span>
            <div class="vi-card-name">Android</div>
            <div class="vi-card-hint">Chrome / Edge</div>
        </div>
        <div class="vi-card c-windows" id="card-windows" onclick="selectPlatform('windows')">
            <span class="vi-detected">Tu dispositivo</span>
            <span class="vi-card-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="M3 12V6.75l6-1.32v6.57H3zm17 0V3.87L11 2.25V12h9zm-17 0v5.25l6 1.32V12H3zm17 0v8.13L11 21.75V12h9z"/></svg>
            </span>
            <div class="vi-card-name">Windows</div>
            <div class="vi-card-hint">Chrome / Edge</div>
        </div>
    </div>

    <!-- iOS -->
    <div class="vi-panel p-ios" id="panel-ios">
        <div class="vi-panel-head">
            <span class="vi-panel-icon" style="color:var(--ios-color)"><svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg></span>
            <div><div class="vi-panel-title" style="color:var(--ios-color)">Instalar en iPhone / iPad</div><div class="vi-panel-sub">Usando Safari — requiere iOS 14 o superior</div></div>
        </div>
        <div class="vi-steps">
            <div class="vi-step"><div class="vi-step-num">1</div><div class="vi-step-body"><div class="vi-step-title">Abre Safari</div><div class="vi-step-desc">La instalación solo funciona desde Safari. Si usas Chrome o Firefox en iOS, abre la misma URL en Safari.</div></div><span class="vi-step-icon">🧭</span></div>
            <div class="vi-step"><div class="vi-step-num">2</div><div class="vi-step-body"><div class="vi-step-title">Ve a la app Vader</div><div class="vi-step-desc">Escribe en Safari: <strong style="color:var(--ios-color)">kmultimedios.com/vader/</strong> y presiona Ir.</div></div><span class="vi-step-icon">🌐</span></div>
            <div class="vi-step"><div class="vi-step-num">3</div><div class="vi-step-body"><div class="vi-step-title">Toca el botón Compartir</div><div class="vi-step-desc">En la barra inferior toca el ícono <strong style="color:var(--ios-color)">□↑</strong> (cuadro con flecha hacia arriba).</div></div><span class="vi-step-icon">⬆️</span></div>
            <div class="vi-step"><div class="vi-step-num">4</div><div class="vi-step-body"><div class="vi-step-title">Toca "Agregar a pantalla de inicio"</div><div class="vi-step-desc">Desplázate en el menú y busca <strong style="color:var(--ios-color)">"Agregar a pantalla de inicio"</strong>.</div></div><span class="vi-step-icon">➕</span></div>
            <div class="vi-step"><div class="vi-step-num">5</div><div class="vi-step-body"><div class="vi-step-title">Confirma y toca "Agregar"</div><div class="vi-step-desc">Puedes cambiar el nombre. Toca <strong style="color:var(--ios-color)">"Agregar"</strong> arriba a la derecha.</div></div><span class="vi-step-icon">✅</span></div>
            <div class="vi-step"><div class="vi-step-num">6</div><div class="vi-step-body"><div class="vi-step-title">¡Listo! Abre la app desde tu pantalla de inicio</div><div class="vi-step-desc">Busca el ícono de KMultimedios y ábrelo — funciona como app nativa.</div></div><span class="vi-step-icon">🚀</span></div>
        </div>
        <div class="vi-note"><strong>Nota:</strong> En iPhone la instalación es solo desde Safari. No aparece en el App Store — esto es normal para PWA.</div>
    </div>

    <!-- Android -->
    <div class="vi-panel p-android" id="panel-android">
        <div class="vi-panel-head">
            <span class="vi-panel-icon" style="color:var(--android-color)"><svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em"><path d="M17.523 15.341c-.41 0-.744-.334-.744-.744V9.743c0-.41.334-.744.744-.744.41 0 .744.334.744.744v4.854c0 .41-.334.744-.744.744m-11.046 0c-.41 0-.744-.334-.744-.744V9.743c0-.41.334-.744.744-.744.41 0 .744.334.744.744v4.854c0 .41-.334.744-.744.744M7.604 6.08l-.864-1.582A.16.16 0 0 1 6.796 4.3a.16.16 0 0 1 .218.058l.876 1.605a5.86 5.86 0 0 1 2.11-.391 5.86 5.86 0 0 1 2.11.391l.876-1.605A.16.16 0 0 1 13.204 4.3a.16.16 0 0 1 .056.198l-.864 1.582C13.666 6.7 14.5 7.636 14.5 8.7H9.5c0-1.064.834-2 1.604-2.62M9.904 7.6a.496.496 0 1 0 0-.992.496.496 0 0 0 0 .992m4.192 0a.496.496 0 1 0 0-.992.496.496 0 0 0 0 .992M6.5 9.2h11v8.4a1.4 1.4 0 0 1-1.4 1.4h-.6v2.256a.744.744 0 1 1-1.488 0V19h-2.024v2.256a.744.744 0 1 1-1.488 0V19h-.6A1.4 1.4 0 0 1 6.5 17.6z"/></svg></span>
            <div><div class="vi-panel-title" style="color:var(--android-color)">Instalar en Android</div><div class="vi-panel-sub">Usando Chrome o Edge — Android 8+</div></div>
        </div>
        <div class="vi-steps">
            <div class="vi-step"><div class="vi-step-num">1</div><div class="vi-step-body"><div class="vi-step-title">Abre Chrome o Edge</div><div class="vi-step-desc">Usa Chrome o Microsoft Edge. En otros navegadores puede no estar disponible.</div></div><span class="vi-step-icon">🌐</span></div>
            <div class="vi-step"><div class="vi-step-num">2</div><div class="vi-step-body"><div class="vi-step-title">Ve a la app Vader</div><div class="vi-step-desc">Escribe: <strong style="color:var(--android-color)">kmultimedios.com/vader/</strong> y presiona Entrar.</div></div><span class="vi-step-icon">📱</span></div>
            <div class="vi-step"><div class="vi-step-num">3</div><div class="vi-step-body"><div class="vi-step-title">Toca el menú ⋮ o el banner de instalación</div><div class="vi-step-desc">Puede aparecer un banner automático. Si no, toca el menú <strong style="color:var(--android-color)">⋮</strong> arriba a la derecha.</div></div><span class="vi-step-icon">⋮</span></div>
            <div class="vi-step"><div class="vi-step-num">4</div><div class="vi-step-body"><div class="vi-step-title">Toca "Instalar aplicación"</div><div class="vi-step-desc">Selecciona <strong style="color:var(--android-color)">"Instalar aplicación"</strong> o <strong style="color:var(--android-color)">"Agregar a pantalla de inicio"</strong>.</div></div><span class="vi-step-icon">⬇️</span></div>
            <div class="vi-step"><div class="vi-step-num">5</div><div class="vi-step-body"><div class="vi-step-title">Confirma tocando "Instalar"</div><div class="vi-step-desc">Toca <strong style="color:var(--android-color)">"Instalar"</strong> y espera unos segundos.</div></div><span class="vi-step-icon">✅</span></div>
            <div class="vi-step"><div class="vi-step-num">6</div><div class="vi-step-body"><div class="vi-step-title">¡Listo! Búscala en tu pantalla de inicio</div><div class="vi-step-desc">El ícono aparecerá en tu pantalla de inicio o cajón de apps.</div></div><span class="vi-step-icon">🚀</span></div>
        </div>
        <button class="vi-install-btn vi-btn-android" id="androidInstallBtn" style="display:none" onclick="triggerInstall()">
            <i class="fas fa-download"></i> Instalar Vader App Ahora
        </button>
        <div class="vi-note"><strong>Tip:</strong> También puedes buscar el ícono <strong style="color:var(--android-color)">⊕</strong> en la barra de direcciones al visitar kmultimedios.com/vader/</div>
    </div>

    <!-- Windows -->
    <div class="vi-panel p-windows" id="panel-windows">
        <div class="vi-panel-head">
            <span class="vi-panel-icon" style="color:var(--windows-color)"><svg viewBox="0 0 24 24" fill="currentColor" width="1em" height="1em"><path d="M3 12V6.75l6-1.32v6.57H3zm17 0V3.87L11 2.25V12h9zm-17 0v5.25l6 1.32V12H3zm17 0v8.13L11 21.75V12h9z"/></svg></span>
            <div><div class="vi-panel-title" style="color:var(--windows-color)">Instalar en Windows</div><div class="vi-panel-sub">Usando Chrome o Microsoft Edge</div></div>
        </div>
        <div class="vi-steps">
            <div class="vi-step"><div class="vi-step-num">1</div><div class="vi-step-body"><div class="vi-step-title">Abre Chrome o Microsoft Edge</div><div class="vi-step-desc">Recomendamos Edge — viene preinstalado en Windows y ofrece la mejor experiencia PWA.</div></div><span class="vi-step-icon">💻</span></div>
            <div class="vi-step"><div class="vi-step-num">2</div><div class="vi-step-body"><div class="vi-step-title">Ve a la app Vader</div><div class="vi-step-desc">Escribe: <strong style="color:var(--windows-color)">kmultimedios.com/vader/</strong> y presiona Enter.</div></div><span class="vi-step-icon">🌐</span></div>
            <div class="vi-step"><div class="vi-step-num">3</div><div class="vi-step-body"><div class="vi-step-title">Busca el ícono ⊕ en la barra de direcciones</div><div class="vi-step-desc">Verás un ícono de instalación. En Edge puede decir <strong style="color:var(--windows-color)">"Aplicación disponible"</strong>. Haz clic.</div></div><span class="vi-step-icon">⊕</span></div>
            <div class="vi-step"><div class="vi-step-num">4</div><div class="vi-step-body"><div class="vi-step-title">Haz clic en "Instalar"</div><div class="vi-step-desc">Confirma haciendo clic en <strong style="color:var(--windows-color)">"Instalar"</strong> en el cuadro que aparece.</div></div><span class="vi-step-icon">✅</span></div>
            <div class="vi-step"><div class="vi-step-num">5</div><div class="vi-step-body"><div class="vi-step-title">¡Listo! Abre desde el escritorio</div><div class="vi-step-desc">La app aparecerá en tu escritorio y menú de inicio como cualquier programa.</div></div><span class="vi-step-icon">🚀</span></div>
        </div>
        <button class="vi-install-btn vi-btn-windows" onclick="window.open('<?php echo esc_url(home_url('/vader/')); ?>','_blank')">
            <i class="fas fa-external-link-alt"></i> Abrir Vader App para Instalar
        </button>
        <div class="vi-note"><strong>Alternativa en Edge:</strong> Ve a <strong style="color:var(--windows-color)">Configuración → Aplicaciones → Instalar este sitio como aplicación</strong> si no ves el ícono.</div>
    </div>

</section>

<!-- CTA -->
<section class="vi-cta">
    <div class="vi-cta-card">
        <h2>¿Ya la instalaste?</h2>
        <p>Únete a la Comunidad KMultimedios — foro gratuito para toda la región fronteriza.</p>
        <div class="vi-cta-btns">
            <a href="https://foro.kmultimedios.com" class="vi-btn-prim"><i class="fas fa-comments"></i> Entrar al Foro</a>
            <a href="<?php echo esc_url(home_url('/vader/')); ?>" class="vi-btn-out"><i class="fas fa-shield-alt"></i> Abrir Vader App</a>
        </div>
    </div>
</section>

<footer class="vi-footer">
    <p>© <?php echo date('Y'); ?> <a href="<?php echo esc_url(home_url('/')); ?>">KMultimedios LLC</a> · <a href="<?php echo esc_url(home_url('/terminos-y-condiciones-de-uso-de-kmultimedios/')); ?>">Términos</a> · <a href="mailto:contacto@kmultimedios.com">Contacto</a></p>
</footer>

<script>
    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        var ab = document.getElementById('androidInstallBtn');
        if (ab) ab.style.display = 'flex';
    });
    function triggerInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(r) {
                deferredPrompt = null;
                var ab = document.getElementById('androidInstallBtn');
                if (ab) { ab.disabled = true; if(r.outcome==='accepted') ab.innerHTML='<i class="fas fa-check"></i> ¡Instalada!'; }
            });
        } else {
            window.open('<?php echo esc_url(home_url('/vader/')); ?>', '_blank');
        }
    }
    function detectPlatform() {
        var ua = navigator.userAgent;
        if (/iPhone|iPad|iPod/.test(ua)) return 'ios';
        if (/Android/.test(ua)) return 'android';
        return 'windows';
    }
    function selectPlatform(p) {
        ['ios','android','windows'].forEach(function(x) {
            document.getElementById('card-'+x).classList.remove('active');
            document.getElementById('panel-'+x).classList.remove('visible');
        });
        document.getElementById('card-'+p).classList.add('active');
        var panel = document.getElementById('panel-'+p);
        panel.classList.add('visible');
        setTimeout(function(){ panel.scrollIntoView({behavior:'smooth',block:'start'}); }, 60);
    }
    (function(){
        var d = detectPlatform();
        document.getElementById('card-'+d).classList.add('detected');
        selectPlatform(d);
    })();
</script>
<?php wp_footer(); ?>
</body>
</html>
