/**
 * cameras.js – Command Center & Radio
 * KMultimedios VIP PWA v2.0
 */

// ── Command Center ─────────────────────────────────────────────────────────────
const CommandCenter = {
  zones:     [],
  watermark: null,
  players:   {}, // { 'cam-code': Hls | 'native' }
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

    const html = this.zones.map(zone => this._zoneHTML(zone)).join('');
    wrap.innerHTML = html;

    // Inicializar todos los players de las cámaras priority:high automáticamente
    this.zones.forEach(zone => {
      zone.sections.forEach(section => {
        section.cameras.forEach(cam => {
          if (cam.priority === 'high') {
            this._initPlayer(cam.code);
          }
        });
      });
    });

    // Watermark
    if (this.watermark) {
      document.querySelectorAll('.cc-watermark').forEach(el => {
        el.textContent = `${this.watermark.email} · ${this.watermark.ip} · ${this.watermark.level}`;
      });
    }
  },

  _zoneHTML(zone) {
    const sections = zone.sections.map(s => this._sectionHTML(s, zone)).join('');
    return `
      <section class="cc-zone">
        <div class="cc-zone__header">
          <span class="cc-zone__code">${zone.zone_code}</span>
          <h2 class="cc-zone__name">${zone.zone_name}</h2>
          <span class="cc-zone__status cc-zone__status--online">${zone.status}</span>
          <span class="cc-zone__traffic">${zone.traffic_level}</span>
        </div>
        ${sections}
      </section>`;
  },

  _sectionHTML(section, zone) {
    const cameras = section.cameras.map(cam => this._cameraHTML(cam)).join('');
    return `
      <div class="cc-section">
        <div class="cc-section__header">
          <i class="fa ${section.icon}"></i>
          <span>${section.title}</span>
          <span class="cc-section__dir">${section.direction}</span>
        </div>
        <div class="cc-cams-grid">
          ${cameras}
        </div>
      </div>`;
  },

  _cameraHTML(cam) {
    const badgeClass = cam.priority === 'high' ? 'cc-badge--high' : 'cc-badge--medium';
    return `
      <div class="cc-cam-card" data-code="${cam.code}" data-hls="${cam.hls_url}">
        <div class="cc-cam-header">
          <span class="cc-cam-code">${cam.code}</span>
          <span class="cc-badge ${badgeClass}">${cam.type}</span>
        </div>
        <div class="cc-cam-wrap" id="wrap-${cam.code}">
          <div class="cc-cam-placeholder" id="placeholder-${cam.code}">
            <button class="cc-play-btn" onclick="CommandCenter._initPlayer('${cam.code}')">
              ▶ Iniciar
            </button>
            <span class="cc-cam-placeholder__name">${cam.name}</span>
          </div>
          <video
            id="video-${cam.code}"
            class="cc-video"
            playsinline
            muted
            style="display:none"
            poster=""
          ></video>
          <div class="cc-watermark cc-watermark--video"></div>
        </div>
        <div class="cc-cam-footer">
          <span class="cc-cam-name">${cam.name}</span>
          <span class="cc-cam-loc">${cam.location}</span>
          <div class="cc-cam-controls">
            <button class="cc-ctrl-btn" title="Reiniciar" onclick="CommandCenter._restartPlayer('${cam.code}')">⟳</button>
            <button class="cc-ctrl-btn" title="Pantalla completa" onclick="CommandCenter._fullscreen('${cam.code}')">⛶</button>
            ${cam.audio_url ? `<button class="cc-ctrl-btn cc-ctrl-btn--audio" title="Audio" onclick="CommandCenter._toggleAudio('${cam.code}', '${cam.audio_url}')">🔇</button>` : ''}
          </div>
        </div>
      </div>`;
  },

  _initPlayer(code) {
    if (this.players[code]) return; // ya iniciado

    const card  = document.querySelector(`[data-code="${code}"]`);
    if (!card) return;

    const hlsUrl      = card.dataset.hls;
    const video       = document.getElementById(`video-${code}`);
    const placeholder = document.getElementById(`placeholder-${code}`);

    if (!video || !hlsUrl) return;

    placeholder.style.display = 'none';
    video.style.display       = 'block';

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
      hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
      hls.on(Hls.Events.ERROR, (_, data) => {
        if (data.fatal) {
          console.warn(`[CC] HLS fatal ${code}:`, data.type);
          hls.destroy();
          delete this.players[code];
          this._showRetry(code);
        }
      });
      this.players[code] = hls;
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      // Safari nativo
      video.src = hlsUrl;
      video.play().catch(() => {});
      this.players[code] = 'native';
    } else {
      this._showRetry(code, 'HLS no soportado en este dispositivo.');
    }
  },

  _restartPlayer(code) {
    const hls = this.players[code];
    if (hls && hls !== 'native') {
      hls.destroy();
    }
    delete this.players[code];

    const video       = document.getElementById(`video-${code}`);
    const placeholder = document.getElementById(`placeholder-${code}`);
    if (video) { video.src = ''; video.style.display = 'none'; }
    if (placeholder) placeholder.style.display = 'flex';

    setTimeout(() => this._initPlayer(code), 300);
  },

  _showRetry(code, msg = 'Error de conexión') {
    const placeholder = document.getElementById(`placeholder-${code}`);
    const video       = document.getElementById(`video-${code}`);
    if (video) video.style.display = 'none';
    if (placeholder) {
      placeholder.style.display = 'flex';
      placeholder.innerHTML = `
        <span class="cc-cam-placeholder__err">⚠ ${msg}</span>
        <button class="cc-play-btn" onclick="CommandCenter._restartPlayer('${code}')">⟳ Reintentar</button>`;
    }
  },

  _fullscreen(code) {
    const video = document.getElementById(`video-${code}`);
    if (!video) return;
    if (video.requestFullscreen)           video.requestFullscreen();
    else if (video.webkitRequestFullscreen) video.webkitRequestFullscreen();
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

  toggle() {
    this.playing ? this.stop() : this.play();
  },

  play(url) {
    const src = url || this.currentUrl;
    this.stop();
    this.audio         = new Audio(src);
    this.audio.volume  = parseFloat(document.getElementById('radio-volume')?.value ?? 0.8);
    this.audio.play().then(() => {
      this.playing = true;
      this._setUI(true);
    }).catch(err => {
      this._setStatus('Error al conectar: ' + err.message);
    });
    this.audio.onerror = () => this._setStatus('⚠ Error de conexión');
  },

  stop() {
    if (this.audio) {
      this.audio.pause();
      this.audio.src = '';
      this.audio = null;
    }
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

// ── Tab switching ──────────────────────────────────────────────────────────────
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

// Expose globally
window.CommandCenter = CommandCenter;
window.RadioPlayer   = RadioPlayer;
window.initTabs      = initTabs;
