/**
 * cameras.js – Command Center & Radio
 * KMultimedios VIP PWA v2.1
 */

// ── Command Center ─────────────────────────────────────────────────────────────
const CommandCenter = {
  zones:     [],
  watermark: null,
  players:   {},
  clockTimer: null,

  async load() {
    const wrap = document.getElementById('cc-zones-wrap');
    if (!wrap) return;

    try {
      const data = await wa._fetch('/streams');
      this.zones     = data.zones     || [];
      this.watermark = data.watermark || null;
      this._render(wrap);
      this._startClock();
    } catch (err) {
      wrap.innerHTML = `<div class="cc-loading-state cc-loading-state--error">
        <p>⚠️ Error cargando cámaras: ${err.message}</p>
        <button class="btn btn--ghost btn--sm" onclick="CommandCenter.load()">Reintentar</button>
      </div>`;
    }
  },

  _render(wrap) {
    if (!this.zones.length) {
      wrap.innerHTML = '<div class="cc-loading-state"><p>Sin zonas disponibles.</p></div>';
      return;
    }

    wrap.innerHTML = this.zones.map(zone => this._zoneHTML(zone)).join('');

    // Arrancar TODAS las cámaras escalonadamente para no saturar
    let delay = 0;
    this.zones.forEach(zone => {
      zone.sections.forEach(section => {
        section.cameras.forEach(cam => {
          setTimeout(() => this._initPlayer(cam.code), delay);
          delay += 400;
        });
      });
    });

    // Watermark
    if (this.watermark) {
      document.querySelectorAll('.cc-wm-email').forEach(el => el.textContent = this.watermark.email);
      document.querySelectorAll('.cc-wm-ip').forEach(el => el.textContent = this.watermark.ip);
      document.querySelectorAll('.cc-wm-level').forEach(el => el.textContent = this.watermark.level);
    }

    this._startWatermarkAnimation();
  },

  _zoneHTML(zone) {
    const sections = zone.sections.map(s => this._sectionHTML(s)).join('');
    return `
      <section class="cc-zone">
        <div class="cc-zone__header">
          <span class="cc-zone__code">${zone.zone_code}</span>
          <h2 class="cc-zone__name">${zone.zone_name}</h2>
          <div class="cc-zone__meta">
            <span class="cc-zone__status">${zone.status}</span>
            <span class="cc-zone__traffic">${zone.traffic_level}</span>
          </div>
        </div>
        ${sections}
      </section>`;
  },

  _sectionHTML(section) {
    const cameras = section.cameras.map(cam => this._cameraHTML(cam)).join('');
    return `
      <div class="cc-section">
        <div class="cc-section__header">
          <i class="fa ${section.icon}"></i>
          <span class="cc-section__title">${section.title}</span>
          <span class="cc-section__dir">${section.direction}</span>
        </div>
        <div class="cc-cams-grid">${cameras}</div>
      </div>`;
  },

  _cameraHTML(cam) {
    return `
      <div class="cam-card" data-code="${cam.code}" data-hls="${cam.hls_url || ''}">
        <div class="cam-header">
          <div class="cam-header__info">
            <span class="cam-id">${cam.code}</span>
            <span class="cam-name">${cam.name}</span>
            <span class="cam-location">📍 ${cam.location}</span>
          </div>
          <span class="cam-type-badge">${cam.type}</span>
        </div>

        <div class="cam-video-wrap" id="wrap-${cam.code}">
          <div class="cam-loading" id="loading-${cam.code}">
            <div class="cam-spinner"></div>
            <span class="cam-loading__text">Conectando…</span>
          </div>
          <video id="video-${cam.code}" class="cam-video" playsinline muted style="display:none"
            oncontextmenu="return false"></video>
          <div class="cam-wm-overlay" id="wm-${cam.code}">
            <div class="cam-wm-top">
              <span class="cam-wm-enc">▣ AES-256</span>
              <span class="cam-wm-deg" id="wm-deg-${cam.code}">000.00°</span>
            </div>
            <div class="cam-wm-mid">
              <span class="cam-wm-email cc-wm-email">—</span>
              <span class="cam-wm-sep">·</span>
              <span class="cam-wm-ip cc-wm-ip">—</span>
            </div>
            <div class="cam-wm-bot">
              <span class="cam-wm-frame" id="wm-frame-${cam.code}">F:000000</span>
              <span class="cam-wm-ts" id="wm-ts-${cam.code}">--:--:--</span>
              <span class="cam-wm-id">${cam.code}</span>
            </div>
          </div>
          <div class="cam-placeholder" id="placeholder-${cam.code}" style="display:none">
            <button class="cc-play-btn" onclick="CommandCenter._restartPlayer('${cam.code}')">
              ▶ Iniciar
            </button>
            <span class="cam-placeholder__name">${cam.name}</span>
          </div>
        </div>

        <div class="cam-session-banner">
          <div class="csb-indicator">
            <div class="csb-rec-dot"></div>
            <span class="csb-rec-label">REC</span>
          </div>
          <div class="csb-body">
            <div class="csb-data-row">
              <span class="csb-field">
                <span class="csb-field-value csb-email cc-wm-email">—</span>
              </span>
              <span class="csb-field">
                <span class="csb-field-value csb-ip cc-wm-ip">—</span>
              </span>
              <span class="csb-field">
                <span class="csb-field-value cc-wm-level">—</span>
              </span>
            </div>
          </div>
        </div>

        <div class="cam-footer">
          <div class="cam-status-bar">
            <span class="cam-status connecting" id="status-${cam.code}">⬤ Conectando</span>
            <div class="cam-controls">
              <button class="cc-ctrl-btn" title="Reiniciar" onclick="CommandCenter._restartPlayer('${cam.code}')">⟳</button>
              <button class="cc-ctrl-btn" title="Pantalla completa" onclick="CommandCenter._fullscreen('${cam.code}')">⛶</button>
              ${cam.audio_url ? `<button class="cc-ctrl-btn cc-ctrl-btn--audio" title="Audio" onclick="CommandCenter._toggleAudio('${cam.code}', '${cam.audio_url}')">🔇</button>` : ''}
            </div>
          </div>
        </div>
      </div>`;
  },

  _initPlayer(code) {
    if (this.players[code]) return;

    const card    = document.querySelector(`[data-code="${code}"]`);
    if (!card) return;

    const hlsUrl      = card.dataset.hls;
    const video       = document.getElementById(`video-${code}`);
    const loading     = document.getElementById(`loading-${code}`);
    const placeholder = document.getElementById(`placeholder-${code}`);
    const statusEl    = document.getElementById(`status-${code}`);

    if (!video || !hlsUrl) {
      if (loading) loading.style.display = 'none';
      if (placeholder) placeholder.style.display = 'flex';
      return;
    }

    const setStatus = (text, cls) => {
      if (!statusEl) return;
      statusEl.textContent = text;
      statusEl.className = `cam-status ${cls}`;
    };

    if (Hls.isSupported()) {
      const hls = new Hls({
        enableWorker:       true,
        lowLatencyMode:     true,
        backBufferLength:   90,
        maxBufferLength:    30,
        maxMaxBufferLength: 60,
      });
      hls.loadSource(hlsUrl);
      hls.attachMedia(video);

      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        if (loading) loading.style.display = 'none';
        video.style.display = 'block';
        video.play().catch(() => {});
        setStatus('⬤ En línea', 'online');
      });

      hls.on(Hls.Events.ERROR, (_, data) => {
        if (data.fatal) {
          hls.destroy();
          delete this.players[code];
          this._showRetry(code);
          setStatus('⬤ Sin señal', 'offline');
        }
      });

      this.players[code] = hls;

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = hlsUrl;
      video.addEventListener('loadeddata', () => {
        if (loading) loading.style.display = 'none';
        video.style.display = 'block';
        setStatus('⬤ En línea', 'online');
      }, { once: true });
      video.play().catch(() => {});
      this.players[code] = 'native';

    } else {
      this._showRetry(code, 'HLS no soportado');
      setStatus('⬤ No soportado', 'offline');
    }
  },

  _restartPlayer(code) {
    const hls = this.players[code];
    if (hls && hls !== 'native') hls.destroy();
    delete this.players[code];

    const video       = document.getElementById(`video-${code}`);
    const loading     = document.getElementById(`loading-${code}`);
    const placeholder = document.getElementById(`placeholder-${code}`);
    const statusEl    = document.getElementById(`status-${code}`);

    if (video)       { video.src = ''; video.style.display = 'none'; }
    if (loading)     { loading.innerHTML = '<div class="cam-spinner"></div><span class="cam-loading__text">Reconectando…</span>'; loading.style.display = 'flex'; }
    if (placeholder) placeholder.style.display = 'none';
    if (statusEl)    { statusEl.textContent = '⬤ Conectando'; statusEl.className = 'cam-status connecting'; }

    setTimeout(() => this._initPlayer(code), 500);
  },

  _showRetry(code, msg = 'Error de conexión') {
    const loading     = document.getElementById(`loading-${code}`);
    const placeholder = document.getElementById(`placeholder-${code}`);
    const video       = document.getElementById(`video-${code}`);
    if (video)       video.style.display = 'none';
    if (loading)     loading.style.display = 'none';
    if (placeholder) {
      placeholder.style.display = 'flex';
      placeholder.innerHTML = `
        <span class="cam-placeholder__err">⚠ ${msg}</span>
        <button class="cc-play-btn" onclick="CommandCenter._restartPlayer('${code}')">⟳ Reintentar</button>`;
    }
  },

  _fullscreen(code) {
    const wrap  = document.getElementById(`wrap-${code}`);
    const video = document.getElementById(`video-${code}`);
    if (!wrap) return;

    const req = wrap.requestFullscreen
              || wrap.webkitRequestFullscreen
              || wrap.mozRequestFullScreen
              || wrap.msRequestFullscreen;

    if (req) {
      req.call(wrap).catch(() => {
        // iOS Safari: fullscreen nativo sobre el elemento <video>
        if (video && video.webkitEnterFullscreen) video.webkitEnterFullscreen();
      });
    } else if (video && video.webkitEnterFullscreen) {
      video.webkitEnterFullscreen();
    }
  },

  _audioPlayers: {},

  _toggleAudio(code, audioUrl) {
    const btn = document.querySelector(`[onclick*="_toggleAudio('${code}'"]`);
    if (this._audioPlayers[code]) {
      this._audioPlayers[code].pause();
      this._audioPlayers[code] = null;
      if (btn) btn.textContent = '🔇';
    } else {
      const audio = new Audio(audioUrl);
      audio.volume = 0.8;
      audio.play().catch(() => {});
      this._audioPlayers[code] = audio;
      if (btn) btn.textContent = '🔊';
    }
  },

  _wmState: {},

  _startWatermarkAnimation() {
    // Each camera gets its own independent deg starting point + frame counter
    this.zones.forEach(zone => {
      zone.sections.forEach(section => {
        section.cameras.forEach(cam => {
          this._wmState[cam.code] = {
            deg:   Math.random() * 360,
            frame: Math.floor(Math.random() * 100000),
          };
        });
      });
    });

    const tick = () => {
      const now = new Date();
      const ts  = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

      for (const code in this._wmState) {
        const s = this._wmState[code];
        s.deg   = (s.deg + 0.07) % 360;   // slow rotation ~4°/s @60fps
        s.frame += 1;

        const degEl   = document.getElementById(`wm-deg-${code}`);
        const frameEl = document.getElementById(`wm-frame-${code}`);
        const tsEl    = document.getElementById(`wm-ts-${code}`);

        if (degEl)   degEl.textContent   = s.deg.toFixed(2) + '°';
        if (frameEl) frameEl.textContent = 'F:' + String(s.frame).padStart(6, '0');
        if (tsEl)    tsEl.textContent    = ts;
      }

      requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  },

  _startClock() {
    const el = document.getElementById('cc-clock');
    if (!el) return;
    const tick = () => {
      el.textContent = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    };
    tick();
    this.clockTimer = setInterval(tick, 1000);
  },
};

// ── Radio Player ──────────────────────────────────────────────────────────────
const RadioPlayer = {
  audio:   null,
  playing: false,
  currentUrl: 'https://streamers.kmultiradio.com/8002/stream',

  init() {
    document.getElementById('radio-play-btn')?.addEventListener('click', () => this.toggle());
    document.getElementById('radio-volume')?.addEventListener('input', e => {
      if (this.audio) this.audio.volume = parseFloat(e.target.value);
    });
    document.querySelectorAll('.radio-station').forEach(btn => {
      btn.addEventListener('click', () => this._switchStation(btn));
    });
  },

  toggle() { this.playing ? this.stop() : this.play(); },

  play(url) {
    const src = url || this.currentUrl;
    this.stop();
    this.audio        = new Audio(src);
    this.audio.volume = parseFloat(document.getElementById('radio-volume')?.value ?? 0.8);
    this.audio.play().then(() => { this.playing = true; this._setUI(true); })
      .catch(err => this._setStatus('Error: ' + err.message));
    this.audio.onerror = () => this._setStatus('⚠ Error de conexión');
  },

  stop() {
    if (this.audio) { this.audio.pause(); this.audio.src = ''; this.audio = null; }
    this.playing = false;
    this._setUI(false);
  },

  _switchStation(btn) {
    document.querySelectorAll('.radio-station').forEach(b => b.classList.remove('radio-station--active'));
    btn.classList.add('radio-station--active');
    this.currentUrl = btn.dataset.url;
    if (this.playing) this.play(this.currentUrl);
  },

  _setUI(isPlaying) {
    const icon = document.getElementById('radio-play-icon');
    const viz  = document.getElementById('radio-visualizer');
    if (icon) icon.textContent = isPlaying ? '⏹' : '▶';
    if (viz)  viz.classList.toggle('radio-visualizer--active', isPlaying);
    this._setStatus(isPlaying ? 'En transmisión…' : 'Listo para reproducir');
  },

  _setStatus(text) {
    const el = document.getElementById('radio-status');
    if (el) el.textContent = text;
  },
};

// ── Tabs ──────────────────────────────────────────────────────────────────────
function initTabs() {
  document.querySelectorAll('.cc-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      document.querySelectorAll('.cc-tab').forEach(t => t.classList.remove('cc-tab--active'));
      tab.classList.add('cc-tab--active');
      document.getElementById('panel-cameras')?.classList.toggle('cc-main--hidden', target !== 'cameras');
      document.getElementById('panel-radio')?.classList.toggle('cc-main--hidden', target !== 'radio');
    });
  });
}

window.CommandCenter = CommandCenter;
window.RadioPlayer   = RadioPlayer;
window.initTabs      = initTabs;
