<?php // Vader App – Página de instalación pública ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Instalar Vader App – KMultimedios</title>
    <meta name="description" content="Instala la app Vader de KMultimedios en tu dispositivo iOS, Android o Windows y accede a las cámaras fronterizas en vivo desde tu pantalla de inicio."/>
    <meta name="robots" content="index, follow"/>
    <link rel="canonical" href="https://kmultimedios.com/instalar-vader/"/>

    <meta property="og:type" content="website"/>
    <meta property="og:url" content="https://kmultimedios.com/instalar-vader/"/>
    <meta property="og:title" content="Instalar Vader App – KMultimedios"/>
    <meta property="og:description" content="Instala la app Vader y accede a las cámaras fronterizas desde tu pantalla de inicio."/>
    <meta property="og:image" content="https://kmultimedios.com/imagenes/seoimg.png"/>
    <meta property="og:site_name" content="KMultimedios"/>

    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&family=Orbitron:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>

    <style>
:root {
  --purple:       #b026ff;
  --purple-dark:  #8b1fc7;
  --purple-dim:   #2d0a5a;
  --purple-glow:  rgba(176, 38, 255, 0.45);
  --green:        #00ff41;
  --green-glow:   rgba(0, 255, 65, 0.4);
  --cyan:         #00d4ff;
  --cyan-glow:    rgba(0, 212, 255, 0.4);
  --bg:           #06000f;
  --surface:      #0f0520;
  --surface2:     #1a0a30;
  --text:         #e8d5ff;
  --text-dim:     #9070b8;
  --ios-color:    #a8b8c8;
  --android-color:#3ddc84;
  --windows-color:#00adef;
  --font-display: 'Orbitron', monospace;
  --font-ui:      'Rajdhani', sans-serif;
  --font-mono:    'Share Tech Mono', monospace;
  --radius:       1rem;
  --transition:   300ms cubic-bezier(0.4, 0, 0.2, 1);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: var(--font-ui); background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; line-height: 1.5; }

.bg-layer { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.bg-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(176,38,255,0.07) 1px, transparent 1px), linear-gradient(90deg, rgba(176,38,255,0.07) 1px, transparent 1px); background-size: 50px 50px; }
.bg-glow { position: absolute; top: -200px; left: 50%; transform: translateX(-50%); width: 800px; height: 600px; background: radial-gradient(ellipse at center, rgba(176,38,255,0.2) 0%, transparent 70%); }

.site-header { position: relative; z-index: 10; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; border-bottom: 1px solid rgba(176,38,255,0.3); background: rgba(6,0,15,0.8); backdrop-filter: blur(20px); }
.header-logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
.header-logo img { height: 36px; filter: drop-shadow(0 0 8px var(--purple-glow)); }
.header-logo span { font-family: var(--font-display); font-size: 1rem; font-weight: 700; color: var(--purple); letter-spacing: 0.05em; }
.header-back { font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-dim); text-decoration: none; display: flex; align-items: center; gap: 0.4rem; transition: color var(--transition); }
.header-back:hover { color: var(--purple); }

.hero { position: relative; z-index: 1; text-align: center; padding: 4rem 1.5rem 3rem; }
.vader-icon { font-size: 5rem; margin-bottom: 1.5rem; filter: drop-shadow(0 0 20px var(--purple-glow)); animation: float 3s ease-in-out infinite; display: block; }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
.hero h1 { font-family: var(--font-display); font-size: clamp(2rem, 6vw, 3.5rem); font-weight: 900; background: linear-gradient(135deg, var(--purple), var(--cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 1rem; letter-spacing: 0.04em; text-transform: uppercase; }
.hero-sub { font-family: var(--font-mono); font-size: 1rem; color: var(--text-dim); max-width: 480px; margin: 0 auto 2.5rem; letter-spacing: 0.04em; line-height: 1.6; }
.hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(176,38,255,0.15); border: 1px solid var(--purple); border-radius: 99px; padding: 0.4rem 1rem; font-family: var(--font-mono); font-size: 0.75rem; color: var(--purple); letter-spacing: 0.08em; }
.hero-badge span { width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 8px var(--green-glow); animation: blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }

.platform-section { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; padding: 0 1.5rem 1.5rem; }
.section-label { font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.15em; text-align: center; margin-bottom: 1.25rem; }
.platform-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2.5rem; }
.platform-card { background: var(--surface); border: 2px solid rgba(176,38,255,0.25); border-radius: var(--radius); padding: 1.75rem 1rem; text-align: center; cursor: pointer; transition: all var(--transition); position: relative; overflow: hidden; }
.platform-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--card-color, var(--purple)), transparent); opacity: 0; transition: opacity var(--transition); }
.platform-card:hover { border-color: var(--card-color, var(--purple)); transform: translateY(-4px); box-shadow: 0 8px 30px rgba(176,38,255,0.2); }
.platform-card.active { border-color: var(--card-color, var(--purple)); background: var(--surface2); box-shadow: 0 0 30px var(--card-glow, var(--purple-glow)), inset 0 0 20px rgba(176,38,255,0.05); }
.platform-card.active::before { opacity: 0.08; }
.card-ios   { --card-color: var(--ios-color);     --card-glow: rgba(168,184,200,0.4); }
.card-android { --card-color: var(--android-color); --card-glow: rgba(61,220,132,0.4); }
.card-windows { --card-color: var(--windows-color); --card-glow: rgba(0,173,239,0.4); }
.platform-icon { font-size: 2.8rem; margin-bottom: 0.75rem; display: block; }
.card-ios .platform-icon     { color: var(--ios-color); }
.card-android .platform-icon { color: var(--android-color); }
.card-windows .platform-icon { color: var(--windows-color); }
.platform-name { font-family: var(--font-display); font-size: 0.85rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
.card-ios .platform-name     { color: var(--ios-color); }
.card-android .platform-name { color: var(--android-color); }
.card-windows .platform-name { color: var(--windows-color); }
.platform-hint { font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-dim); margin-top: 0.35rem; }
.detected-badge { position: absolute; top: 0.5rem; right: 0.5rem; background: var(--green); color: #000; font-family: var(--font-mono); font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 99px; text-transform: uppercase; letter-spacing: 0.05em; display: none; }
.platform-card.detected .detected-badge { display: block; }

.instructions-panel { display: none; background: var(--surface); border: 2px solid rgba(176,38,255,0.3); border-radius: var(--radius); padding: 2rem; margin-bottom: 2rem; animation: slideIn 0.3s ease; }
.instructions-panel.visible { display: block; }
@keyframes slideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.panel-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid rgba(176,38,255,0.2); }
.panel-icon { font-size: 2rem; }
.panel-title { font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.panel-subtitle { font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-dim); margin-top: 0.2rem; }

.steps { display: flex; flex-direction: column; gap: 1rem; }
.step { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 1.25rem; background: rgba(176,38,255,0.05); border: 1px solid rgba(176,38,255,0.15); border-radius: 0.75rem; transition: all var(--transition); }
.step:hover { background: rgba(176,38,255,0.1); border-color: rgba(176,38,255,0.35); }
.step-num { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 0.9rem; font-weight: 900; flex-shrink: 0; }
.panel-ios .step-num     { background: rgba(168,184,200,0.15); color: var(--ios-color); border: 2px solid var(--ios-color); }
.panel-android .step-num { background: rgba(61,220,132,0.15); color: var(--android-color); border: 2px solid var(--android-color); }
.panel-windows .step-num { background: rgba(0,173,239,0.15); color: var(--windows-color); border: 2px solid var(--windows-color); }
.step-body { flex: 1; }
.step-title { font-family: var(--font-ui); font-size: 1.05rem; font-weight: 700; color: var(--text); margin-bottom: 0.25rem; }
.step-desc  { font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-dim); line-height: 1.55; }
.step-icon  { font-size: 1.4rem; flex-shrink: 0; align-self: center; }

.install-prompt-btn { width: 100%; margin-top: 1.5rem; padding: 1rem; background: linear-gradient(135deg, #1a4a2e, #0d2a1a); border: 2px solid var(--android-color); border-radius: var(--radius); color: var(--android-color); font-family: var(--font-display); font-size: 0.95rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.75rem; transition: all var(--transition); text-transform: uppercase; letter-spacing: 0.05em; }
.install-prompt-btn:hover { box-shadow: 0 0 25px rgba(61,220,132,0.4); transform: translateY(-2px); }
.install-prompt-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.btn-windows { background: linear-gradient(135deg, #0a2a3a, #051a2a); border-color: var(--windows-color); color: var(--windows-color); }
.btn-windows:hover { box-shadow: 0 0 25px rgba(0,173,239,0.4); }

.panel-note { margin-top: 1.25rem; padding: 0.75rem 1rem; background: rgba(0,255,65,0.06); border-left: 3px solid var(--green); border-radius: 0 0.5rem 0.5rem 0; font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-dim); line-height: 1.6; }
.panel-note strong { color: var(--green); }

.cta-section { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; padding: 1rem 1.5rem 4rem; text-align: center; }
.cta-card { background: linear-gradient(135deg, var(--purple-dim), var(--surface2)); border: 2px solid var(--purple); border-radius: var(--radius); padding: 2.5rem; position: relative; overflow: hidden; }
.cta-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--purple), transparent); animation: scan 3s linear infinite; }
@keyframes scan { 0%{transform:translateX(-100%);} 100%{transform:translateX(100%);} }
.cta-card h2 { font-family: var(--font-display); font-size: 1.4rem; font-weight: 800; color: var(--purple); margin-bottom: 0.75rem; text-transform: uppercase; }
.cta-card p { font-family: var(--font-mono); font-size: 0.88rem; color: var(--text-dim); margin-bottom: 1.75rem; line-height: 1.6; }
.cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.btn-primary { background: var(--purple); color: white; padding: 0.8rem 1.75rem; border-radius: 99px; text-decoration: none; font-family: var(--font-ui); font-size: 1rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; transition: all var(--transition); box-shadow: 0 0 20px var(--purple-glow); }
.btn-primary:hover { background: var(--purple-dark); transform: translateY(-2px); }
.btn-outline { background: transparent; color: var(--cyan); padding: 0.8rem 1.75rem; border: 2px solid var(--cyan); border-radius: 99px; text-decoration: none; font-family: var(--font-ui); font-size: 1rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; transition: all var(--transition); }
.btn-outline:hover { background: var(--cyan); color: #000; transform: translateY(-2px); }

.site-footer { position: relative; z-index: 1; text-align: center; padding: 1.5rem; border-top: 1px solid rgba(176,38,255,0.2); font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-dim); }
.site-footer a { color: var(--purple); text-decoration: none; }

@media (max-width: 600px) {
  .platform-cards { grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
  .platform-card { padding: 1.25rem 0.5rem; }
  .platform-icon { font-size: 2rem; }
  .platform-name { font-size: 0.7rem; }
  .platform-hint { display: none; }
  .site-header { padding: 0.75rem 1rem; }
  .hero { padding: 2.5rem 1rem 2rem; }
  .instructions-panel { padding: 1.25rem; }
  .step { padding: 0.75rem 1rem; }
}
    </style>
</head>
<body>

<div class="bg-layer">
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>
</div>

<header class="site-header">
    <a href="https://kmultimedios.com" class="header-logo">
        <img src="https://kmultimedios.com/logosk/kmultimedios.png" alt="KMultimedios" onerror="this.style.display='none'">
        <span>KMULTIMEDIOS</span>
    </a>
    <a href="https://foro.kmultimedios.com" class="header-back">
        <i class="fas fa-arrow-left"></i> Volver al Foro
    </a>
</header>

<section class="hero">
    <span class="vader-icon">🛡️</span>
    <h1>Instala Vader App</h1>
    <p class="hero-sub">Accede a las cámaras fronterizas en tiempo real desde tu pantalla de inicio — sin tiendas de apps, gratis.</p>
    <div class="hero-badge"><span></span> Disponible para iOS · Android · Windows</div>
</section>

<section class="platform-section">
    <p class="section-label">Selecciona tu dispositivo</p>

    <div class="platform-cards">
        <div class="platform-card card-ios" id="card-ios" onclick="selectPlatform('ios')">
            <span class="detected-badge">Tu dispositivo</span>
            <span class="platform-icon"><i class="fab fa-apple"></i></span>
            <div class="platform-name">iPhone / iPad</div>
            <div class="platform-hint">iOS 14+</div>
        </div>
        <div class="platform-card card-android" id="card-android" onclick="selectPlatform('android')">
            <span class="detected-badge">Tu dispositivo</span>
            <span class="platform-icon"><i class="fab fa-android"></i></span>
            <div class="platform-name">Android</div>
            <div class="platform-hint">Chrome / Edge</div>
        </div>
        <div class="platform-card card-windows" id="card-windows" onclick="selectPlatform('windows')">
            <span class="detected-badge">Tu dispositivo</span>
            <span class="platform-icon"><i class="fab fa-windows"></i></span>
            <div class="platform-name">Windows</div>
            <div class="platform-hint">Chrome / Edge</div>
        </div>
    </div>

    <!-- iOS -->
    <div class="instructions-panel panel-ios" id="panel-ios">
        <div class="panel-header">
            <span class="panel-icon" style="color:var(--ios-color)"><i class="fab fa-apple"></i></span>
            <div>
                <div class="panel-title" style="color:var(--ios-color)">Instalar en iPhone / iPad</div>
                <div class="panel-subtitle">Usando Safari — requiere iOS 14 o superior</div>
            </div>
        </div>
        <div class="steps">
            <div class="step"><div class="step-num">1</div><div class="step-body"><div class="step-title">Abre Safari</div><div class="step-desc">La instalación solo funciona desde Safari. Si estás en Chrome o Firefox en iOS, cópialo y ábrelo en Safari.</div></div><span class="step-icon">🧭</span></div>
            <div class="step"><div class="step-num">2</div><div class="step-body"><div class="step-title">Ve a la app Vader</div><div class="step-desc">Escribe en la barra de Safari: <strong style="color:var(--ios-color)">kmultimedios.com/vader/</strong> y presiona Ir.</div></div><span class="step-icon">🌐</span></div>
            <div class="step"><div class="step-num">3</div><div class="step-body"><div class="step-title">Toca el botón Compartir</div><div class="step-desc">En la barra inferior de Safari toca el ícono <strong style="color:var(--ios-color)">□↑</strong> (cuadro con flecha hacia arriba).</div></div><span class="step-icon">⬆️</span></div>
            <div class="step"><div class="step-num">4</div><div class="step-body"><div class="step-title">Toca "Agregar a pantalla de inicio"</div><div class="step-desc">Desplázate y busca <strong style="color:var(--ios-color)">"Agregar a pantalla de inicio"</strong>. Tócala.</div></div><span class="step-icon">➕</span></div>
            <div class="step"><div class="step-num">5</div><div class="step-body"><div class="step-title">Confirma y toca "Agregar"</div><div class="step-desc">Puedes cambiar el nombre. Luego toca <strong style="color:var(--ios-color)">"Agregar"</strong> en la esquina superior derecha.</div></div><span class="step-icon">✅</span></div>
            <div class="step"><div class="step-num">6</div><div class="step-body"><div class="step-title">¡Listo! Abre la app desde tu pantalla de inicio</div><div class="step-desc">Busca el ícono de KMultimedios y ábrelo — funciona como una app nativa.</div></div><span class="step-icon">🚀</span></div>
        </div>
        <div class="panel-note"><strong>Nota:</strong> En iPhone la app se instala únicamente desde Safari. No aparece en el App Store — esto es normal para PWA.</div>
    </div>

    <!-- Android -->
    <div class="instructions-panel panel-android" id="panel-android">
        <div class="panel-header">
            <span class="panel-icon" style="color:var(--android-color)"><i class="fab fa-android"></i></span>
            <div>
                <div class="panel-title" style="color:var(--android-color)">Instalar en Android</div>
                <div class="panel-subtitle">Usando Chrome o Edge — Android 8+</div>
            </div>
        </div>
        <div class="steps">
            <div class="step"><div class="step-num">1</div><div class="step-body"><div class="step-title">Abre Chrome o Edge</div><div class="step-desc">Asegúrate de usar Chrome o Microsoft Edge. En otros navegadores puede no estar disponible.</div></div><span class="step-icon">🌐</span></div>
            <div class="step"><div class="step-num">2</div><div class="step-body"><div class="step-title">Ve a la app Vader</div><div class="step-desc">Escribe: <strong style="color:var(--android-color)">kmultimedios.com/vader/</strong> y presiona Entrar.</div></div><span class="step-icon">📱</span></div>
            <div class="step"><div class="step-num">3</div><div class="step-body"><div class="step-title">Toca el menú ⋮ o el banner de instalación</div><div class="step-desc">Puede aparecer un banner automático. Si no, toca el menú <strong style="color:var(--android-color)">⋮</strong> arriba a la derecha.</div></div><span class="step-icon">⋮</span></div>
            <div class="step"><div class="step-num">4</div><div class="step-body"><div class="step-title">Toca "Instalar aplicación"</div><div class="step-desc">Selecciona <strong style="color:var(--android-color)">"Instalar aplicación"</strong> o <strong style="color:var(--android-color)">"Agregar a pantalla de inicio"</strong>.</div></div><span class="step-icon">⬇️</span></div>
            <div class="step"><div class="step-num">5</div><div class="step-body"><div class="step-title">Confirma tocando "Instalar"</div><div class="step-desc">Toca <strong style="color:var(--android-color)">"Instalar"</strong> y espera unos segundos.</div></div><span class="step-icon">✅</span></div>
            <div class="step"><div class="step-num">6</div><div class="step-body"><div class="step-title">¡Listo! Búscala en tu pantalla de inicio</div><div class="step-desc">El ícono aparecerá en tu pantalla de inicio o cajón de apps.</div></div><span class="step-icon">🚀</span></div>
        </div>
        <button class="install-prompt-btn" id="androidInstallBtn" style="display:none" onclick="triggerInstall()">
            <i class="fas fa-download"></i> Instalar Vader App Ahora
        </button>
        <div class="panel-note"><strong>Tip:</strong> También puedes buscar el ícono <strong style="color:var(--android-color)">⊕</strong> en la barra de direcciones al visitar kmultimedios.com/vader/</div>
    </div>

    <!-- Windows -->
    <div class="instructions-panel panel-windows" id="panel-windows">
        <div class="panel-header">
            <span class="panel-icon" style="color:var(--windows-color)"><i class="fab fa-windows"></i></span>
            <div>
                <div class="panel-title" style="color:var(--windows-color)">Instalar en Windows</div>
                <div class="panel-subtitle">Usando Chrome o Microsoft Edge</div>
            </div>
        </div>
        <div class="steps">
            <div class="step"><div class="step-num">1</div><div class="step-body"><div class="step-title">Abre Chrome o Microsoft Edge</div><div class="step-desc">Recomendamos Edge — viene preinstalado en Windows y ofrece la mejor experiencia de instalación PWA.</div></div><span class="step-icon">💻</span></div>
            <div class="step"><div class="step-num">2</div><div class="step-body"><div class="step-title">Ve a la app Vader</div><div class="step-desc">Escribe en la barra de direcciones: <strong style="color:var(--windows-color)">kmultimedios.com/vader/</strong> y presiona Enter.</div></div><span class="step-icon">🌐</span></div>
            <div class="step"><div class="step-num">3</div><div class="step-body"><div class="step-title">Haz clic en el botón de instalar</div><div class="step-desc">O presiona el botón de abajo para ir directamente a instalar la app.</div></div><span class="step-icon">📌</span></div>
            <div class="step"><div class="step-num">4</div><div class="step-body"><div class="step-title">Busca el ícono ⊕ en la barra de direcciones</div><div class="step-desc">Verás un ícono de instalación en la barra. En Edge puede decir <strong style="color:var(--windows-color)">"Aplicación disponible"</strong>. Haz clic en él.</div></div><span class="step-icon">⊕</span></div>
            <div class="step"><div class="step-num">5</div><div class="step-body"><div class="step-title">Haz clic en "Instalar"</div><div class="step-desc">Confirma haciendo clic en <strong style="color:var(--windows-color)">"Instalar"</strong> en el cuadro que aparece.</div></div><span class="step-icon">✅</span></div>
            <div class="step"><div class="step-num">6</div><div class="step-body"><div class="step-title">¡Listo! Abre la app desde el escritorio</div><div class="step-desc">La app aparecerá en tu escritorio y menú de inicio como cualquier programa.</div></div><span class="step-icon">🚀</span></div>
        </div>
        <button class="install-prompt-btn btn-windows" onclick="window.open('https://kmultimedios.com/vader/','_blank')">
            <i class="fas fa-external-link-alt"></i> Abrir Vader App para Instalar
        </button>
        <div class="panel-note"><strong>Alternativa en Edge:</strong> Ve a <strong style="color:var(--windows-color)">Configuración → Aplicaciones → Instalar este sitio como aplicación</strong> si no ves el ícono en la barra.</div>
    </div>

</section>

<section class="cta-section">
    <div class="cta-card">
        <h2>¿Ya la instalaste?</h2>
        <p>Únete a la Comunidad KMultimedios — foro gratuito para toda la región fronteriza. Comparte, comenta y entérate de lo que pasa en la frontera.</p>
        <div class="cta-buttons">
            <a href="https://foro.kmultimedios.com" class="btn-primary">
                <i class="fas fa-comments"></i> Entrar al Foro
            </a>
            <a href="https://kmultimedios.com/vader/" class="btn-outline">
                <i class="fas fa-shield-alt"></i> Abrir Vader App
            </a>
        </div>
    </div>
</section>

<footer class="site-footer">
    <p>© <?php echo date('Y'); ?> <a href="https://kmultimedios.com">KMultimedios LLC</a> · <a href="https://kmultimedios.com/terminos-y-condiciones-de-uso-de-kmultimedios/">Términos</a> · <a href="mailto:contacto@kmultimedios.com">Contacto</a></p>
</footer>

<script>
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        var ab = document.getElementById('androidInstallBtn');
        if (ab) ab.style.display = 'flex';
    });

    function triggerInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(result) {
                deferredPrompt = null;
                var ab = document.getElementById('androidInstallBtn');
                if (ab) { ab.disabled = true; ab.innerHTML = '<i class="fas fa-check"></i> ¡Instalada!'; }
            });
        } else {
            window.open('https://kmultimedios.com/vader/', '_blank');
        }
    }

    function detectPlatform() {
        var ua = navigator.userAgent;
        if (/iPhone|iPad|iPod/.test(ua)) return 'ios';
        if (/Android/.test(ua)) return 'android';
        return 'windows';
    }

    function selectPlatform(platform) {
        ['ios', 'android', 'windows'].forEach(function(p) {
            document.getElementById('card-' + p).classList.remove('active');
            document.getElementById('panel-' + p).classList.remove('visible');
        });
        document.getElementById('card-' + platform).classList.add('active');
        var panel = document.getElementById('panel-' + platform);
        panel.classList.add('visible');
        setTimeout(function() {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
    }

    (function() {
        var detected = detectPlatform();
        document.getElementById('card-' + detected).classList.add('detected');
        selectPlatform(detected);
    })();
</script>
</body>
</html>
