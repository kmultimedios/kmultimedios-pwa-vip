<?php
/**
 * Template Name: KM Monitor de Cruces Fronterizos
 * Version: 6.1 - Real-Time Border Crossing Information + WATERMARK DISUASORIO
 *
 * @package KMultimedios
 * @since 6.1
 */

// ============================================
// WATERMARK / SESSION DATA (primero, antes del HTML)
// ============================================
$current_user = wp_get_current_user();

$wm_email = ($current_user && !empty($current_user->user_email))
    ? $current_user->user_email
    : 'usuario@desconocido';

$wm_ip_raw = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';

if (strpos($wm_ip_raw, ',') !== false) {
    $wm_ip_raw = trim(explode(',', $wm_ip_raw)[0]);
}

// Mostrar IP completa — es el mayor disuasivo
$wm_ip = $wm_ip_raw;

$wm_uid      = get_current_user_id();
$wm_time     = wp_date('Y-m-d H:i:s');
$wm_user     = $current_user->display_name ?: $current_user->user_login ?: 'Usuario';
// Membership level name (si PMPro existe)
$wm_level    = '';
if (function_exists('pmpro_getMembershipLevelForUser')) {
    $lvl = pmpro_getMembershipLevelForUser($wm_uid);
    if ($lvl) $wm_level = strtoupper($lvl->name);
}

// 🔐 Validación VIP
$has_vip = false;
if (function_exists('pmpro_hasMembershipLevel')) {
    $has_vip = pmpro_hasMembershipLevel([4, 5, 8, 9, 10, 11, 12]);
}

if (!$has_vip && !current_user_can('manage_options')) {
    get_header(); ?>
    <div style="max-width:900px;margin:60px auto;padding:30px;border:2px solid #00ff41;background:rgba(0,0,0,.65);box-shadow:0 0 30px rgba(0,255,65,.25);color:#e0e0e0;">
      <h2 style="font-family:Orbitron,monospace;color:#00f0ff;text-transform:uppercase;letter-spacing:.08em;">
        Acceso VIP requerido
      </h2>
      <p style="margin-top:10px;color:#bdbdbd;">
        Necesitas una membresía VIP activa para ver el Monitor de Cruces Fronterizos.
      </p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px;">
        <a href="<?php echo esc_url(home_url('/membresias/')); ?>"
           style="padding:12px 18px;border:1px solid #00ff41;color:#00ff41;text-decoration:none;font-weight:700;">
          Ver planes VIP
        </a>
        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"
           style="padding:12px 18px;border:1px solid #00f0ff;color:#00f0ff;text-decoration:none;font-weight:700;">
          Iniciar sesión
        </a>
      </div>
    </div>
    <?php get_footer();
    exit;
}

defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    auth_redirect();
    exit;
}

get_header();

global $post;

// ============================================
// CONFIGURACIÓN DE ZONAS Y CÁMARAS
// ============================================
$border_zones = [
    'douglas_ap' => [
        'zone_name'     => 'Douglas - Agua Prieta',
        'zone_code'     => 'DGL-AP',
        'coordinates'   => ['lat' => 31.3448, 'lng' => -109.5456],
        'status'        => 'EN LÍNEA',
        'traffic_level' => 'FLUIDO',
        'sections' => [
            [
                'title'     => 'Douglas → Agua Prieta',
                'direction' => 'SOUTHBOUND',
                'icon'      => 'fa-arrow-down',
                'cameras'   => [
                    [
                        'hls_key'   => 'proxy_e285327477b6d4ff',
                        'name'      => 'Border Mart',
                        'code'      => 'DGL-BM-01',
                        'location'  => 'Douglas AZ',
                        'type'      => 'COMERCIAL',
                        'priority'  => 'high',
                        'audio_url' => 'https://streamers.kmultiradio.com/8002/stream'
                    ],
                    [
                        'hls_key'   => 'proxy_148a60d3da5b4f5f',
                        'name'      => 'Cruce Panamericana',
                        'code'      => 'DGL-PJ-02',
                        'location'  => 'Douglas AZ',
                        'type'      => 'TRÁFICO',
                        'priority'  => 'medium',
                        'audio_url' => 'https://streamers.kmultiradio.com/8002/stream'
                    ]
                ]
            ],
            [
                'title'     => 'Agua Prieta → Douglas',
                'direction' => 'NORTHBOUND',
                'icon'      => 'fa-arrow-up',
                'cameras'   => [
                    [
                        'hls_key'   => 'proxy_dd020195b1320f75',
                        'name'      => 'Cruce Peatonal',
                        'code'      => 'AP-PE-01',
                        'location'  => 'Agua Prieta SON',
                        'type'      => 'PEATONES',
                        'priority'  => 'high',
                        'audio_url' => 'https://streamers.kmultiradio.com/8002/stream'
                    ],
                    [
                        'hls_key'   => 'proxy_eee9c86c2c3c67b8',
                        'name'      => 'Carriles Oeste',
                        'code'      => 'AP-VW-02',
                        'location'  => 'Agua Prieta SON',
                        'type'      => 'VEHÍCULOS',
                        'priority'  => 'high',
                        'audio_url' => 'https://streamers.kmultiradio.com/8002/stream'
                    ],
                    [
                        'hls_key'   => 'proxy_4065bd7c5d3a9e9e',
                        'name'      => 'Carriles Este',
                        'code'      => 'AP-VE-03',
                        'location'  => 'Agua Prieta SON',
                        'type'      => 'VEHÍCULOS',
                        'priority'  => 'medium',
                        'audio_url' => 'https://streamers.kmultiradio.com/8002/stream'
                    ]
                ]
            ]
        ]
    ],
    'nogales' => [
        'zone_name'     => 'Nogales Sonora - Arizona',
        'zone_code'     => 'NOG-MRP',
        'coordinates'   => ['lat' => 31.3404, 'lng' => -110.9398],
        'status'        => 'EN LÍNEA',
        'traffic_level' => 'MODERADO',
        'sections' => [
            [
                'title'     => 'Garita Mariposa',
                'direction' => 'NORTHBOUND',
                'icon'      => 'fa-arrow-up',
                'cameras'   => [
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/mariposa/general/index.m3u8',
                        'name'     => 'Mariposa Vista General',
                        'code'     => 'MRP-GV-01',
                        'location' => 'Nogales AZ',
                        'type'     => 'PANORÁMICA',
                        'priority' => 'high'
                    ],
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/pla-sur/index.m3u8',
                        'name'     => 'Mariposa Plataforma Sur',
                        'code'     => 'MRP-SP-02',
                        'location' => 'Nogales AZ',
                        'type'     => 'PLATAFORMA',
                        'priority' => 'medium'
                    ]
                ]
            ],
            [
                'title'     => 'Garita DeConcini',
                'direction' => 'NORTHBOUND',
                'icon'      => 'fa-arrow-up',
                'cameras'   => [
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/norte/index.m3u8',
                        'name'     => 'DeConcini Acceso Norte',
                        'code'     => 'DCC-NA-01',
                        'location' => 'Nogales AZ',
                        'type'     => 'ACCESO',
                        'priority' => 'high'
                    ],
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/sur/index.m3u8',
                        'name'     => 'DeConcini Acceso Sur',
                        'code'     => 'DCC-SA-02',
                        'location' => 'Nogales AZ',
                        'type'     => 'ACCESO',
                        'priority' => 'high'
                    ],
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/ban-nor/index.m3u8',
                        'name'     => 'DeConcini Carril Sur 2',
                        'code'     => 'DCC-SL-03',
                        'location' => 'Nogales AZ',
                        'type'     => 'CARRIL',
                        'priority' => 'medium'
                    ],
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/hot-nor/index.m3u8',
                        'name'     => 'SENTRI Acceso Norte',
                        'code'     => 'SNT-NA-01',
                        'location' => 'Nogales AZ',
                        'type'     => 'SENTRI',
                        'priority' => 'medium'
                    ],
                    [
                        'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/hot-sur/index.m3u8',
                        'name'     => 'SENTRI Acceso Sur',
                        'code'     => 'SNT-SA-02',
                        'location' => 'Nogales AZ',
                        'type'     => 'SENTRI',
                        'priority' => 'medium'
                    ]
                ]
            ]
        ]
    ]
];

$stream_nonce = wp_create_nonce('km_stream_access');
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Monitor de Cruces Fronterizos en Tiempo Real - KMultimedios VIP">

    <link rel="preconnect" href="https://videoplus.kmultimedios.com">
    <link rel="preconnect" href="https://cruce.heroicanogales.gob.mx">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.12/dist/hls.min.js" defer></script>

    <?php wp_head(); ?>
</head>

<body <?php body_class('command-center'); ?>>

<style>
/* ============================================
   VARIABLES
   ============================================ */
:root {
  --cc-black:        #000000;
  --cc-dark:         #0a0e1a;
  --cc-darker:       #050810;
  --cc-surface:      #0f1419;
  --cc-surface-light:#1a1f2e;
  --cc-neon-green:   #00ff41;
  --cc-green:        #00cc33;
  --cc-green-glow:   rgba(0,255,65,.5);
  --cc-cyan:         #00f0ff;
  --cc-cyan-glow:    rgba(0,240,255,.5);
  --cc-danger:       #ff0033;
  --cc-danger-glow:  rgba(255,0,51,.5);
  --cc-warning:      #ffaa00;
  --cc-warning-glow: rgba(255,170,0,.5);
  --cc-amber:        #ffb300;
  --cc-text:         #e0e0e0;
  --cc-text-dim:     #808080;
  --cc-grid:         rgba(0,255,65,.1);
  --cc-grid-bright:  rgba(0,255,65,.3);
  --font-display:    'Orbitron', monospace;
  --font-heading:    'Rajdhani', sans-serif;
  --font-mono:       'Share Tech Mono', monospace;
  --sp-xs: .25rem; --sp-sm: .5rem; --sp-md: 1rem;
  --sp-lg: 1.5rem; --sp-xl: 2rem; --sp-2xl: 3rem;
  --tr-fast: 150ms cubic-bezier(.4,0,.2,1);
  --tr-base: 300ms cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--font-heading);line-height:1.5;color:var(--cc-text);background:var(--cc-black);overflow-x:hidden;position:relative}

/* ============================================
   BACKGROUND
   ============================================ */
.cc-bg{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;overflow:hidden}
.grid-layer{position:absolute;inset:0;background-image:linear-gradient(var(--cc-grid) 1px,transparent 1px),linear-gradient(90deg,var(--cc-grid) 1px,transparent 1px);background-size:50px 50px;animation:gridPulse 4s ease-in-out infinite}
@keyframes gridPulse{0%,100%{opacity:.3}50%{opacity:.6}}
.scanline{position:absolute;top:0;left:0;width:100%;height:3px;background:linear-gradient(to bottom,transparent,var(--cc-neon-green),transparent);box-shadow:0 0 20px var(--cc-green-glow);animation:scanlineMove 4s linear infinite}
@keyframes scanlineMove{0%{transform:translateY(0)}100%{transform:translateY(100vh)}}
.radar-sweep{position:absolute;top:50%;left:50%;width:800px;height:800px;margin:-400px 0 0 -400px;border-radius:50%;background:conic-gradient(from 0deg,transparent 0deg,var(--cc-green-glow) 20deg,transparent 40deg);animation:radarRotate 8s linear infinite;opacity:.1}
@keyframes radarRotate{to{transform:rotate(360deg)}}

/* ============================================
   MAIN
   ============================================ */
.cc-wrap{position:relative;z-index:1;min-height:100vh;padding:var(--sp-xl)}

/* ============================================
   HEADER
   ============================================ */
.cc-header{margin-bottom:var(--sp-2xl);border:2px solid var(--cc-green);background:linear-gradient(135deg,var(--cc-darker),var(--cc-surface));padding:var(--sp-xl);position:relative;overflow:hidden;box-shadow:0 0 30px var(--cc-green-glow),inset 0 0 50px rgba(0,255,65,.05)}
.cc-header::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--cc-neon-green),transparent);animation:headerScan 3s linear infinite}
@keyframes headerScan{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
.cc-header-top{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-lg);margin-bottom:var(--sp-lg);flex-wrap:wrap}
.cc-title-group{flex:1;min-width:300px}
.cc-badge{display:inline-flex;align-items:center;gap:var(--sp-sm);background:var(--cc-danger);color:#fff;padding:var(--sp-xs) var(--sp-md);font-family:var(--font-mono);font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:var(--sp-md);box-shadow:0 0 20px var(--cc-danger-glow);animation:badgePulse 2s infinite}
@keyframes badgePulse{0%,100%{opacity:1}50%{opacity:.7}}
.cc-title{font-family:var(--font-display);font-size:clamp(2rem,5vw,3.5rem);font-weight:900;letter-spacing:.05em;text-transform:uppercase;background:linear-gradient(135deg,var(--cc-neon-green),var(--cc-cyan));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:var(--sp-sm);line-height:1.1}
.cc-subtitle{font-family:var(--font-mono);font-size:.875rem;color:var(--cc-text-dim);letter-spacing:.15em;text-transform:uppercase}
.cc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--sp-md);margin-top:var(--sp-lg)}
.stat-box{background:rgba(0,255,65,.05);border:1px solid var(--cc-green);padding:var(--sp-md);text-align:center;position:relative;overflow:hidden}
.stat-box::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(0,255,65,.2),transparent);animation:statSweep 3s infinite}
@keyframes statSweep{to{left:100%}}
.stat-label{font-family:var(--font-mono);font-size:.625rem;color:var(--cc-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:var(--sp-xs)}
.stat-value{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--cc-neon-green);text-shadow:0 0 10px var(--cc-green-glow)}
.system-status{display:flex;align-items:center;gap:var(--sp-md);flex-wrap:wrap}
.status-item{display:flex;align-items:center;gap:var(--sp-sm);font-family:var(--font-mono);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em}
.status-dot{width:8px;height:8px;border-radius:50%;animation:statusBlink 2s infinite}
.status-dot.active{background:var(--cc-neon-green);box-shadow:0 0 10px var(--cc-green-glow)}
.status-dot.warning{background:var(--cc-warning);box-shadow:0 0 10px var(--cc-warning-glow)}
@keyframes statusBlink{0%,100%{opacity:1}50%{opacity:.3}}

/* ============================================
   ZONES
   ============================================ */
.zone-section{margin-bottom:var(--sp-2xl);border:2px solid var(--cc-cyan);background:linear-gradient(135deg,var(--cc-darker),var(--cc-surface));padding:var(--sp-xl);position:relative;box-shadow:0 0 30px var(--cc-cyan-glow)}
.zone-header{display:grid;grid-template-columns:auto 1fr auto;gap:var(--sp-lg);align-items:center;margin-bottom:var(--sp-xl);padding-bottom:var(--sp-lg);border-bottom:1px solid var(--cc-grid-bright)}
.zone-icon{width:80px;height:80px;background:var(--cc-cyan);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--cc-black);clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);box-shadow:0 0 30px var(--cc-cyan-glow)}
.zone-info h2{font-family:var(--font-display);font-size:2rem;font-weight:800;color:var(--cc-cyan);text-transform:uppercase;letter-spacing:.1em;margin-bottom:var(--sp-xs);text-shadow:0 0 20px var(--cc-cyan-glow)}
.zone-code{font-family:var(--font-mono);font-size:.875rem;color:var(--cc-text-dim);letter-spacing:.2em}
.zone-status-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--sp-sm);font-family:var(--font-mono);font-size:.75rem}
.zone-stat{display:flex;justify-content:space-between;padding:var(--sp-sm);background:rgba(0,240,255,.05);border-left:2px solid var(--cc-cyan)}
.zone-stat-label{color:var(--cc-text-dim);text-transform:uppercase}
.zone-stat-value{color:var(--cc-cyan);font-weight:700}
.subsection-header{display:flex;align-items:center;gap:var(--sp-md);margin:var(--sp-xl) 0 var(--sp-lg);padding:var(--sp-md);background:linear-gradient(90deg,rgba(0,255,65,.1),transparent);border-left:4px solid var(--cc-neon-green)}
.subsection-icon{font-size:1.5rem;color:var(--cc-neon-green)}
.subsection-title{font-family:var(--font-heading);font-size:1.5rem;font-weight:700;color:var(--cc-neon-green);text-transform:uppercase;letter-spacing:.05em}
.direction-badge{margin-left:auto;padding:var(--sp-xs) var(--sp-md);background:var(--cc-neon-green);color:var(--cc-black);font-family:var(--font-mono);font-size:.625rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}

/* ============================================
   CAMERA GRID & CARD
   ============================================ */
.camera-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:var(--sp-lg)}
.camera-card{background:var(--cc-surface);border:1px solid var(--cc-green);position:relative;overflow:hidden;transition:all var(--tr-base);box-shadow:0 0 20px rgba(0,255,65,.2)}
.camera-card::before{content:'';position:absolute;top:-2px;left:-2px;right:-2px;bottom:-2px;background:linear-gradient(45deg,var(--cc-neon-green),var(--cc-cyan),var(--cc-neon-green));background-size:300% 300%;animation:borderGlow 3s ease infinite;opacity:0;z-index:-1;transition:opacity var(--tr-base)}
.camera-card:hover::before{opacity:.5}
@keyframes borderGlow{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
.camera-card:hover{transform:translateY(-4px);box-shadow:0 0 40px rgba(0,255,65,.4);border-color:var(--cc-cyan)}

.cam-header{background:linear-gradient(135deg,rgba(0,255,65,.1),rgba(0,240,255,.1));padding:var(--sp-md);border-bottom:1px solid var(--cc-grid-bright);display:grid;grid-template-columns:1fr auto;gap:var(--sp-md);align-items:start}
.cam-id{font-family:var(--font-mono);font-size:.625rem;color:var(--cc-text-dim);text-transform:uppercase;letter-spacing:.15em;margin-bottom:var(--sp-xs)}
.cam-name{font-family:var(--font-heading);font-size:1.125rem;font-weight:700;color:var(--cc-text);text-transform:uppercase;margin-bottom:var(--sp-xs)}
.cam-location{font-family:var(--font-mono);font-size:.75rem;color:var(--cc-text-dim);display:flex;align-items:center;gap:var(--sp-xs)}
.cam-type-badge{padding:var(--sp-xs) var(--sp-sm);background:var(--cc-cyan);color:var(--cc-black);font-family:var(--font-mono);font-size:.625rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}

/* Video */
.cam-video-wrap{position:relative;background:var(--cc-black);aspect-ratio:16/9;overflow:hidden}
.cam-video{width:100%;height:100%;object-fit:contain;display:block}
.cam-loading{position:absolute;inset:0;background:rgba(0,0,0,.9);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:var(--sp-md);z-index:5}
.loading-spinner{width:60px;height:60px;border:3px solid rgba(0,255,65,.2);border-top-color:var(--cc-neon-green);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-text{font-family:var(--font-mono);font-size:.75rem;color:var(--cc-text-dim);text-transform:uppercase;letter-spacing:.1em}

/* ============================================
   WATERMARK DISUASORIO - SOLO EN BANNER INFERIOR
   ============================================ */

/* ============================================
   SESSION MONITOR BANNER
   ============================================ */
.cam-session-banner {
  display: flex;
  align-items: stretch;
  background: linear-gradient(90deg, #0a0e00, #0d1200, #0a0e00);
  border-top:    1px solid rgba(0, 255, 65, 0.35);
  border-bottom: 1px solid rgba(0, 255, 65, 0.20);
  font-family: var(--font-mono);
  font-size: 0;
  overflow: hidden;
  position: relative;
  user-select: none;
  -webkit-user-select: none;
}

.csb-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 0 10px;
  background: rgba(0,255,65,.07);
  border-right: 1px solid rgba(0,255,65,.25);
  flex-shrink: 0;
}
.csb-rec-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--cc-danger);
  box-shadow: 0 0 6px var(--cc-danger-glow);
  animation: recBlink 1.4s ease-in-out infinite;
}
@keyframes recBlink{0%,100%{opacity:1}50%{opacity:.2}}
.csb-rec-label {
  font-family: var(--font-mono);
  font-size: 7px;
  color: var(--cc-danger);
  letter-spacing: .08em;
  text-transform: uppercase;
}

.csb-body {
  flex: 1;
  min-width: 0;
  padding: 6px 10px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.csb-alert-row {
  display: flex;
  align-items: center;
  gap: 6px;
}
.csb-alert-icon {
  font-size: 9px;
  color: var(--cc-warning);
  flex-shrink: 0;
}
.csb-alert-text {
  font-size: 9px;
  font-weight: 700;
  color: var(--cc-warning);
  text-transform: uppercase;
  letter-spacing: .12em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Animación de grados rotando para watermark */
.wm-rotating-deg {
  display: inline-block;
  font-family: var(--font-mono);
  color: var(--cc-neon-green);
  font-weight: 700;
  min-width: 35px;
  text-align: center;
}

@keyframes degreePulse {
  0%, 100% { 
    color: var(--cc-neon-green);
    text-shadow: 0 0 8px var(--cc-green-glow);
  }
  50% { 
    color: var(--cc-cyan);
    text-shadow: 0 0 12px var(--cc-cyan-glow);
  }
}

.csb-data-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0 14px;
}
.csb-field {
  display: flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}
.csb-field-icon {
  font-size: 8px;
  color: var(--cc-text-dim);
  flex-shrink: 0;
}
.csb-field-label {
  font-size: 8px;
  color: var(--cc-text-dim);
  text-transform: uppercase;
  letter-spacing: .08em;
}
.csb-field-value {
  font-size: 9.5px;
  color: var(--cc-neon-green);
  font-weight: 700;
  letter-spacing: .04em;
}
.csb-field-value.csb-ip {
  color: var(--cc-cyan);
  font-size: 10px;
}
.csb-field-value.csb-email {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.csb-time-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  padding: 6px 10px;
  border-left: 1px solid rgba(0,255,65,.18);
  gap: 2px;
  flex-shrink: 0;
}
.csb-time-label {
  font-size: 7px;
  color: var(--cc-text-dim);
  text-transform: uppercase;
  letter-spacing: .1em;
}
.csb-time-value {
  font-size: 9.5px;
  color: var(--cc-neon-green);
  font-weight: 700;
  letter-spacing: .04em;
  white-space: nowrap;
}

/* ============================================
   CAM FOOTER
   ============================================ */
.cam-footer{padding:var(--sp-md);background:rgba(0,0,0,.5);border-top:1px solid var(--cc-grid-bright)}
.cam-status-bar{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-md);margin-bottom:var(--sp-md);flex-wrap:wrap}
.cam-status{display:flex;align-items:center;gap:var(--sp-sm);font-family:var(--font-mono);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em}
.cam-status.online{color:var(--cc-neon-green)}
.cam-status.connecting{color:var(--cc-warning)}
.cam-status.offline{color:var(--cc-danger)}
.cam-metrics{display:flex;gap:var(--sp-md);font-family:var(--font-mono);font-size:.625rem;color:var(--cc-text-dim)}
.cam-metric{display:flex;align-items:center;gap:var(--sp-xs)}
.cam-actions{display:flex;gap:var(--sp-sm)}
.cam-btn{flex:1;padding:var(--sp-sm) var(--sp-md);background:transparent;border:1px solid var(--cc-green);color:var(--cc-green);font-family:var(--font-mono);font-size:.625rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all var(--tr-fast);display:flex;align-items:center;justify-content:center;gap:var(--sp-xs)}
.cam-btn:hover{background:var(--cc-green);color:var(--cc-black);box-shadow:0 0 20px var(--cc-green-glow)}
.cam-btn:active{transform:scale(.95)}

/* ============================================
   FULLSCREEN
   ============================================ */
.cam-video-wrap.is-fullscreen {
  position: fixed !important;
  top: 0 !important; left: 0 !important;
  width: 100vw !important; height: 100vh !important;
  z-index: 99998 !important;
  background: #000 !important;
  aspect-ratio: unset !important;
}
.cam-video-wrap.is-fullscreen .cam-video {
  width: 100% !important; height: 100% !important;
  object-fit: contain !important;
}
.cam-video-wrap.is-fullscreen .cam-fs-banner {
  display: flex !important;
}
.cam-fs-banner {
  display: none;
  position: absolute;
  bottom: 0; left: 0; right: 0;
  z-index: 99999;
  background: linear-gradient(0deg, rgba(0,0,0,.92) 0%, rgba(0,0,0,.60) 70%, transparent 100%);
  padding: 28px 20px 14px;
  flex-direction: column;
  gap: 5px;
  pointer-events: none;
  user-select: none;
  -webkit-user-select: none;
}
.cam-fs-banner-alert {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 700;
  color: var(--cc-warning);
  text-transform: uppercase;
  letter-spacing: .14em;
  display: flex;
  align-items: center;
  gap: 7px;
}
.cam-fs-banner-data {
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--cc-neon-green);
  display: flex;
  flex-wrap: wrap;
  gap: 6px 20px;
  align-items: center;
}
.cam-fs-banner-data .fs-sep {
  color: rgba(0,255,65,.35);
  font-size: 10px;
}
.cam-fs-banner-data .fs-ip {
  color: var(--cc-cyan);
  font-weight: 700;
  font-size: 13px;
}
.cam-fs-banner-data .fs-time {
  color: #aaa;
  font-size: 11px;
}

/* ============================================
   TOAST
   ============================================ */
.toast-container{position:fixed;top:var(--sp-xl);right:var(--sp-xl);z-index:9999;display:flex;flex-direction:column;gap:var(--sp-md);max-width:400px}
.toast{background:var(--cc-surface);border:1px solid var(--cc-green);padding:var(--sp-md);box-shadow:0 0 30px var(--cc-green-glow);display:flex;align-items:start;gap:var(--sp-md);animation:slideIn .3s ease-out}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
.toast.success{border-color:var(--cc-green)}.toast.error{border-color:var(--cc-danger)}.toast.warning{border-color:var(--cc-warning)}
.toast-icon{font-size:1.25rem;flex-shrink:0}
.toast.success .toast-icon{color:var(--cc-neon-green)}.toast.error .toast-icon{color:var(--cc-danger)}
.toast-content{flex:1;font-family:var(--font-mono)}
.toast-title{font-size:.875rem;font-weight:700;color:var(--cc-text);margin-bottom:var(--sp-xs);text-transform:uppercase}
.toast-message{font-size:.75rem;color:var(--cc-text-dim)}
.toast-close{background:none;border:none;color:var(--cc-text-dim);cursor:pointer;font-size:1rem;padding:0;transition:color var(--tr-fast)}
.toast-close:hover{color:var(--cc-text)}

/* ============================================
   RESPONSIVE
   ============================================ */
@media(max-width:1024px){.camera-grid{grid-template-columns:repeat(auto-fill,minmax(350px,1fr))}}
@media(max-width:768px){
  .cc-wrap{padding:var(--sp-md)}
  .camera-grid{grid-template-columns:1fr}
  .zone-header{grid-template-columns:1fr;text-align:center}
  .zone-icon{margin:0 auto}
  .zone-status-grid{grid-template-columns:1fr}
  .toast-container{left:var(--sp-md);right:var(--sp-md);max-width:none}
  .watermark-disuasorio {
    bottom: 50px !important;
    right: 10px !important;
    font-size: 9px !important;
    padding: 8px 12px !important;
    max-width: 220px;
  }
  .csb-field.csb-hide-sm { display: none; }
  .csb-field-value.csb-email { max-width: 120px; }
}

/* ============================================
   ACCESSIBILITY
   ============================================ */
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border-width:0}
*:focus-visible{outline:2px solid var(--cc-cyan);outline-offset:2px}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>

<!-- Background -->
<div class="cc-bg" aria-hidden="true">
  <div class="grid-layer"></div>
  <div class="scanline"></div>
  <div class="radar-sweep"></div>
</div>

<div class="toast-container" id="toastContainer" role="status" aria-live="polite"></div>

<main class="cc-wrap" role="main">

  <!-- Header -->
  <header class="cc-header">
    <div class="cc-header-top">
      <div class="cc-title-group">
        <div class="cc-badge">
          <div class="status-dot active"></div>
          <span>ACCESO VIP - KMULTIMEDIOS</span>
        </div>
        <h1 class="cc-title">Monitor de Cruces Fronterizos</h1>
        <p class="cc-subtitle">Información en Tiempo Real</p>
      </div>
      <div class="system-status">
        <div class="status-item"><div class="status-dot active"></div><span>Sistema Activo</span></div>
        <div class="status-item"><div class="status-dot active"></div><span>Transmisión En Vivo</span></div>
        <div class="status-item"><div class="status-dot warning"></div><span>Conexión Segura</span></div>
      </div>
    </div>
    <div class="cc-stats" id="globalStats">
      <div class="stat-box"><div class="stat-label">Total Cámaras</div><div class="stat-value" id="totalCameras">--</div></div>
      <div class="stat-box"><div class="stat-label">Transmisiones Activas</div><div class="stat-value" id="activeStreams">--</div></div>
      <div class="stat-box"><div class="stat-label">Ubicaciones</div><div class="stat-value"><?php echo count($border_zones); ?></div></div>
      <div class="stat-box"><div class="stat-label">Estado</div><div class="stat-value">EN LÍNEA</div></div>
    </div>
  </header>

  <?php foreach ($border_zones as $zone_id => $zone): ?>
  <section class="zone-section" id="zone-<?php echo esc_attr($zone_id); ?>">
    <div class="zone-header">
      <div class="zone-icon"><i class="fas fa-shield-alt"></i></div>
      <div class="zone-info">
        <h2><?php echo esc_html($zone['zone_name']); ?></h2>
        <div class="zone-code">CODE: <?php echo esc_html($zone['zone_code']); ?></div>
      </div>
      <div class="zone-status-grid">
        <div class="zone-stat"><span class="zone-stat-label">Estado</span><span class="zone-stat-value"><?php echo esc_html($zone['status']); ?></span></div>
        <div class="zone-stat"><span class="zone-stat-label">Tráfico</span><span class="zone-stat-value"><?php echo esc_html($zone['traffic_level']); ?></span></div>
        <div class="zone-stat"><span class="zone-stat-label">Lat</span><span class="zone-stat-value"><?php echo esc_html($zone['coordinates']['lat']); ?></span></div>
        <div class="zone-stat"><span class="zone-stat-label">Lng</span><span class="zone-stat-value"><?php echo esc_html($zone['coordinates']['lng']); ?></span></div>
      </div>
    </div>

    <?php foreach ($zone['sections'] as $section): ?>
    <div class="subsection-header">
      <i class="fas <?php echo esc_attr($section['icon']); ?> subsection-icon"></i>
      <h3 class="subsection-title"><?php echo esc_html($section['title']); ?></h3>
      <div class="direction-badge"><?php echo esc_html($section['direction']); ?></div>
    </div>

    <div class="camera-grid">
      <?php foreach ($section['cameras'] as $camera): ?>
      <article
        class="camera-card"
        data-hls-key="<?php echo esc_attr($camera['hls_key'] ?? ''); ?>"
        data-hls-url="<?php echo esc_attr($camera['hls_url'] ?? ''); ?>"
        data-priority="<?php echo esc_attr($camera['priority'] ?? 'medium'); ?>"
        data-audio-url="<?php echo esc_attr($camera['audio_url'] ?? ''); ?>"
        data-nonce="<?php echo esc_attr($stream_nonce); ?>"
      >
        <div class="cam-header">
          <div>
            <div class="cam-id"><?php echo esc_html($camera['code']); ?></div>
            <h4 class="cam-name"><?php echo esc_html($camera['name']); ?></h4>
            <div class="cam-location">
              <i class="fas fa-map-marker-alt"></i>
              <span><?php echo esc_html($camera['location']); ?></span>
            </div>
          </div>
          <div class="cam-type-badge"><?php echo esc_html($camera['type']); ?></div>
        </div>

        <div class="cam-video-wrap">
          <video
            class="cam-video"
            controls
            playsinline
            preload="metadata"
            aria-label="Cámara en vivo: <?php echo esc_attr($camera['name']); ?>"
          >Tu navegador no soporta reproducción de video.</video>

          <!-- Banner fullscreen -->
          <div class="cam-fs-banner" aria-hidden="true">
            <div class="cam-fs-banner-alert">
              <i class="fas fa-shield-alt"></i>
              ⚠️ WATERMARK INVISIBLE ACTIVO <span class="wm-rotating-deg">0°</span> · RANDOM POSITION TRACKING · SESIÓN MONITOREADA
            </div>
            <div class="cam-fs-banner-data">
              <span><i class="fas fa-user" style="color:#666;font-size:10px;margin-right:4px"></i><?php echo esc_html($wm_email); ?></span>
              <span class="fs-sep">|</span>
              <span class="fs-ip"><i class="fas fa-network-wired" style="font-size:10px;margin-right:4px"></i><?php echo esc_html($wm_ip); ?></span>
              <span class="fs-sep">|</span>
              <span>UID <?php echo esc_html($wm_uid); ?></span>
              <?php if ($wm_level): ?>
              <span class="fs-sep">|</span>
              <span><?php echo esc_html($wm_level); ?></span>
              <?php endif; ?>
              <span class="fs-sep">|</span>
              <span class="fs-time wm-time-fs"><?php echo esc_html($wm_time); ?></span>
            </div>
          </div>

          <div class="cam-loading">
            <div class="loading-spinner"></div>
            <p class="loading-text">Estableciendo Conexión...</p>
          </div>
        </div>

        <!-- SESSION MONITOR BANNER -->
        <div class="cam-session-banner" aria-label="Información de sesión" aria-hidden="true">
          <div class="csb-indicator">
            <div class="csb-rec-dot"></div>
            <div class="csb-rec-label">REC</div>
          </div>
          <div class="csb-body">
            <div class="csb-alert-row">
              <i class="fas fa-lock csb-alert-icon"></i>
              <span class="csb-alert-text">
                ⚠️ WATERMARK INVISIBLE ACTIVO <span class="wm-rotating-deg">0°</span> · RANDOM POSITION TRACKING · SESIÓN MONITOREADA
              </span>
            </div>
            <div class="csb-data-row">
              <div class="csb-field">
                <i class="fas fa-user csb-field-icon"></i>
                <span class="csb-field-label">Usuario:</span>
                <span class="csb-field-value csb-email"><?php echo esc_html($wm_email); ?></span>
              </div>
              <div class="csb-field">
                <i class="fas fa-network-wired csb-field-icon"></i>
                <span class="csb-field-label">IP:</span>
                <span class="csb-field-value csb-ip"><?php echo esc_html($wm_ip); ?></span>
              </div>
              <div class="csb-field csb-hide-sm">
                <i class="fas fa-id-badge csb-field-icon"></i>
                <span class="csb-field-label">UID:</span>
                <span class="csb-field-value"><?php echo esc_html($wm_uid); ?></span>
              </div>
              <?php if ($wm_level): ?>
              <div class="csb-field csb-hide-sm">
                <i class="fas fa-star csb-field-icon"></i>
                <span class="csb-field-label">Plan:</span>
                <span class="csb-field-value"><?php echo esc_html($wm_level); ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="csb-time-col">
            <span class="csb-time-label">Hora sesión</span>
            <span class="csb-time-value wm-time"><?php echo esc_html($wm_time); ?></span>
          </div>
        </div>

        <div class="cam-footer">
          <div class="cam-status-bar">
            <div class="cam-status connecting">
              <div class="status-dot"></div>
              <span class="status-text">Conectando...</span>
            </div>
            <div class="cam-metrics">
              <div class="cam-metric"><i class="fas fa-signal"></i><span class="quality-text">--</span></div>
              <div class="cam-metric"><i class="fas fa-clock"></i><span class="latency-text">--</span></div>
            </div>
          </div>
          <div class="cam-actions">
            <button class="cam-btn refresh-btn" aria-label="Refrescar">
              <i class="fas fa-sync-alt"></i><span>Refrescar</span>
            </button>
            <button class="cam-btn fullscreen-btn" aria-label="Pantalla Completa">
              <i class="fas fa-expand"></i><span>Expandir</span>
            </button>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

</main>

<script>
/**
 * Command Center Stream Manager v6.1
 */
const CommandCenter = (() => {
  'use strict';

  const config = {
    proxyUrl: 'https://videoplus.kmultimedios.com',
    maxRetries: 3,
    retryDelay: 3000,
    hlsConfig: {
      debug: false,
      lowLatencyMode: true,
      liveSyncDurationCount: 3,
      maxBufferLength: 10,
      enableWorker: true,
      startLevel: -1,
      autoStartLoad: true
    }
  };

  const state = {
    cameras: new Map(),
    activeStreams: 0,
    totalCameras: 0
  };

  const utils = {
    showToast(message, type, title) {
      type  = type  || 'info';
      title = title || '';
      const container = document.getElementById('toastContainer');
      if (!container) return;

      const icons = {
        success: 'fa-check-circle',
        error:   'fa-exclamation-triangle',
        warning: 'fa-exclamation-circle',
        info:    'fa-info-circle'
      };

      const toast = document.createElement('div');
      toast.className = 'toast ' + type;
      toast.innerHTML =
        '<i class="fas ' + (icons[type] || icons.info) + ' toast-icon"></i>' +
        '<div class="toast-content">' +
          (title ? '<div class="toast-title">' + title + '</div>' : '') +
          '<div class="toast-message">' + message + '</div>' +
        '</div>' +
        '<button class="toast-close" aria-label="Close"><i class="fas fa-times"></i></button>';

      container.appendChild(toast);

      var timeout = setTimeout(function() {
        toast.style.animation = 'slideIn .3s ease-out reverse';
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
      }, 5000);

      var closeBtn = toast.querySelector('.toast-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', function() {
          clearTimeout(timeout);
          if (toast.parentNode) toast.remove();
        });
      }
    },

    log(message, type, data) {
      var prefix = '[CC ' + new Date().toISOString() + ']';
      if (type === 'error') { console.error(prefix, message, data || ''); }
      else if (type === 'warn') { console.warn(prefix, message, data || ''); }
      else { console.log(prefix, message, data || ''); }
    }
  };

  const cameraManager = {
    updateUI(card, updates) {
      if (updates.status) {
        var st = card.querySelector('.status-text');
        var si = card.querySelector('.cam-status');
        if (st) st.textContent = updates.status.text;
        if (si) si.className = 'cam-status ' + updates.status.className;
      }
      if (updates.quality) {
        var qt = card.querySelector('.quality-text');
        if (qt) qt.textContent = updates.quality;
      }
      if (updates.latency) {
        var lt = card.querySelector('.latency-text');
        if (lt) lt.textContent = updates.latency;
      }
      if (updates.loading !== undefined) {
        var ld = card.querySelector('.cam-loading');
        if (ld) ld.style.display = updates.loading ? 'flex' : 'none';
      }
    },

    async initCamera(card) {
      var hlsKey   = card.dataset.hlsKey;
      var hlsUrl   = card.dataset.hlsUrl;
      var video    = card.querySelector('video');
      if (!video) { utils.log('Video element not found', 'error'); return; }

      var streamUrl = hlsKey
        ? config.proxyUrl + '/hls/protected/' + hlsKey + '.m3u8'
        : hlsUrl;

      utils.log('Initializing camera: ' + streamUrl);

      try {
        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
          await this.initHLS(card, video, streamUrl, hlsKey || hlsUrl);
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
          await this.initNative(card, video, streamUrl, hlsKey || hlsUrl);
        } else {
          throw new Error('HLS not supported');
        }
      } catch(e) {
        utils.log('Error initializing camera', 'error', e);
        this.handleError(card, e, hlsKey || hlsUrl);
      }
    },

    async initHLS(card, video, streamUrl, cameraId) {
      var hls = new Hls(config.hlsConfig);
      var retryCount = 0;
      var self = this;

      hls.loadSource(streamUrl);
      hls.attachMedia(video);

      hls.on(Hls.Events.MANIFEST_PARSED, function() {
        self.updateUI(card, { status: { text: 'Listo', className: 'online' }, loading: false, quality: 'Auto' });
        state.activeStreams++;
        self.updateGlobalStats();
      });

      hls.on(Hls.Events.LEVEL_SWITCHED, function(e, data) {
        var level = hls.levels[data.level];
        if (level) self.updateUI(card, { quality: level.height + 'p' });
      });

      hls.on(Hls.Events.FRAG_LOADED, function(e, data) {
        if (data.stats) {
          var lat = Math.round(data.stats.loading.end - data.stats.loading.start);
          self.updateUI(card, { latency: lat + 'ms' });
        }
      });

      hls.on(Hls.Events.ERROR, function(e, data) {
        if (data.fatal) {
          if (data.type === Hls.ErrorTypes.NETWORK_ERROR && retryCount < config.maxRetries) {
            retryCount++;
            setTimeout(function() { hls.startLoad(); }, config.retryDelay);
          } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
            hls.recoverMediaError();
          } else {
            self.handleError(card, new Error(data.details), cameraId);
          }
        }
      });

      state.cameras.set(cameraId, { hls: hls, card: card, video: video, startTime: Date.now() });
      this.setupVideoListeners(card, video, cameraId);
    },

    async initNative(card, video, streamUrl, cameraId) {
      var self = this;
      video.src = streamUrl;

      video.addEventListener('loadedmetadata', function() {
        self.updateUI(card, { status: { text: 'Listo', className: 'online' }, loading: false });
        state.activeStreams++;
        self.updateGlobalStats();
      });

      video.addEventListener('error', function() {
        self.handleError(card, new Error('Playback error'), cameraId);
      });

      state.cameras.set(cameraId, { card: card, video: video, native: true, startTime: Date.now() });
      this.setupVideoListeners(card, video, cameraId);
    },

    pauseAllOtherCameras(currentCameraId) {
      state.cameras.forEach(function(cam, id) {
        if (id !== currentCameraId) {
          if (cam.video && !cam.video.paused) cam.video.pause();
          if (cam.radioAudio && !cam.radioAudio.paused) cam.radioAudio.pause();
        }
      });
    },

    setupVideoListeners(card, video, cameraId) {
      var self     = this;
      var audioUrl = card.dataset.audioUrl;
      var radioAudio = null;

      if (audioUrl) {
        radioAudio        = new Audio(audioUrl);
        radioAudio.preload = 'auto';
        radioAudio.volume  = 1.0;
        video.muted        = true;
        video.volume       = 0;
        var cam = state.cameras.get(cameraId);
        if (cam) cam.radioAudio = radioAudio;
      }

      video.addEventListener('play', function() {
        self.pauseAllOtherCameras(cameraId);
        self.updateUI(card, { status: { text: 'En Vivo', className: 'online' } });
        if (radioAudio) radioAudio.play().catch(function(e) { utils.log('Audio play error', 'warn', e); });
      });

      video.addEventListener('pause', function() {
        self.updateUI(card, { status: { text: 'Pausado', className: 'connecting' } });
        if (radioAudio) radioAudio.pause();
      });

      video.addEventListener('waiting', function() { self.updateUI(card, { loading: true }); });
      video.addEventListener('playing', function() { self.updateUI(card, { loading: false }); });

      this.setupActionButtons(card, video, cameraId);
    },

    setupActionButtons(card, video, cameraId) {
      var self = this;

      var refreshBtn = card.querySelector('.refresh-btn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() { self.refreshCamera(card, cameraId); });
      }

      var fullscreenBtn = card.querySelector('.fullscreen-btn');
      if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
          var wrap = card.querySelector('.cam-video-wrap');
          self.toggleFullscreen(wrap || video, fullscreenBtn);
        });
      }
    },

    async refreshCamera(card, cameraId) {
      var cam = state.cameras.get(cameraId);
      if (!cam) return;
      utils.showToast('Refrescando transmisión...', 'info');
      if (cam.hls) cam.hls.destroy();
      state.cameras.delete(cameraId);
      state.activeStreams--;
      this.updateGlobalStats();
      await this.initCamera(card);
    },

    toggleFullscreen(wrap, btn) {
      var isFs = wrap.classList.contains('is-fullscreen');

      document.querySelectorAll('.cam-video-wrap.is-fullscreen').forEach(function(el) {
        el.classList.remove('is-fullscreen');
        document.body.style.overflow = '';
        var b = el.closest && el.closest('.camera-card')
                  ? el.closest('.camera-card').querySelector('.fullscreen-btn i')
                  : null;
        if (b) b.className = 'fas fa-expand';
      });

      if (!isFs) {
        wrap.classList.add('is-fullscreen');
        document.body.style.overflow = 'hidden';
        if (btn) btn.querySelector('i').className = 'fas fa-compress';
        var req = wrap.requestFullscreen || wrap.webkitRequestFullscreen || wrap.mozRequestFullScreen;
        if (req) req.call(wrap).catch(function(){});
      } else {
        var exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen;
        if (exit && document.fullscreenElement) exit.call(document).catch(function(){});
      }
    },

    handleError(card, error, cameraId) {
      utils.log('Camera error', 'error', { cameraId: cameraId, error: error.message });
      this.updateUI(card, { status: { text: 'Sin Conexión', className: 'offline' }, loading: false });
      utils.showToast('No se pudo conectar al stream. Haz clic en refrescar.', 'error', 'Error de Conexión');
    },

    updateGlobalStats() {
      var t = document.getElementById('totalCameras');
      var a = document.getElementById('activeStreams');
      if (t) t.textContent = state.totalCameras;
      if (a) a.textContent = state.activeStreams;
    }
  };

  function init() {
    utils.log('Monitor de Cruces iniciando...');

    if (typeof Hls === 'undefined') {
      utils.log('HLS.js not loaded', 'error');
      utils.showToast('Error crítico: reproductor de video no disponible.', 'error', 'Error del Sistema');
      return;
    }

    var cards = document.querySelectorAll('.camera-card');
    state.totalCameras = cards.length;
    cameraManager.updateGlobalStats();

    var high = [], med = [], low = [];
    cards.forEach(function(card) {
      var p = card.dataset.priority || 'medium';
      if (p === 'high') high.push(card);
      else if (p === 'low') low.push(card);
      else med.push(card);
    });

    var ordered = high.concat(med).concat(low);
    ordered.forEach(function(card, i) {
      setTimeout(function() { cameraManager.initCamera(card); }, i * 200);
    });

    utils.showToast('Inicializando ' + state.totalCameras + ' cámaras en vivo...', 'info', 'Sistema Iniciado');
  }

  return { init: init };
})();

(function() {
  var pad = function(n) { return String(n).padStart(2, '0'); };
  setInterval(function() {
    var now = new Date();
    var ts  = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + ' '
            + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    document.querySelectorAll('.wm-time, .wm-time-fs').forEach(function(el) {
      if (el) el.textContent = ts;
    });
  }, 1000);
})();

/* Animación de grados rotando (WATERMARK) */
(function() {
  var currentDegree = 0;
  var direction = 1;
  
  setInterval(function() {
    currentDegree += (Math.random() * 8 + 2) * direction;
    
    if (currentDegree >= 360) {
      currentDegree = 360;
      direction = -1;
    } else if (currentDegree <= 0) {
      currentDegree = 0;
      direction = 1;
    }
    
    var degreeText = Math.floor(currentDegree) + '°';
    document.querySelectorAll('.wm-rotating-deg').forEach(function(el) {
      if (el) el.textContent = degreeText;
    });
  }, 150);
})();

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' || e.keyCode === 27) {
    document.querySelectorAll('.cam-video-wrap.is-fullscreen').forEach(function(wrap) {
      wrap.classList.remove('is-fullscreen');
      document.body.style.overflow = '';
      var icon = wrap.closest && wrap.closest('.camera-card')
                   ? wrap.closest('.camera-card').querySelector('.fullscreen-btn i')
                   : null;
      if (icon) icon.className = 'fas fa-expand';
    });
  }
});

document.addEventListener('fullscreenchange', function() {
  if (!document.fullscreenElement) {
    document.querySelectorAll('.cam-video-wrap.is-fullscreen').forEach(function(wrap) {
      wrap.classList.remove('is-fullscreen');
      document.body.style.overflow = '';
    });
  }
});

function tryInit(n) {
  n = n || 0;
  if (n > 50) { console.error('[CC] HLS.js timeout'); return; }
  if (typeof Hls === 'undefined') {
    setTimeout(function() { tryInit(n + 1); }, 100);
    return;
  }
  try { CommandCenter.init(); } catch(e) { console.error('[CC]', e); }
}

window.addEventListener('load', function() { tryInit(0); });
window.CommandCenter = CommandCenter;
</script>

<?php wp_footer(); ?>
</body>
</html>
