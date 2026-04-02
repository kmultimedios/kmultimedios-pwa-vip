/**
 * App Principal – KMultimedios VIP Windows (vaderwin)
 * Optimizado para escritorio: incluye radio, layout amplio.
 */

const API_BASE = '/wp-json/pwa/v1';

const App = {
  currentScreen: null,

  showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => {
      s.classList.toggle('active', s.id === `screen-${id}`);
    });
    this.currentScreen = id;
    window.scrollTo(0, 0);
  },

  goTo(section) {
    const screenMap = {
      home:     'home',
      cameras:  'main',
      video:    'video',
      radio:    'radio',
      settings: 'settings',
      devices:  'devices',
    };
    this.showScreen(screenMap[section] || section);

    if (section === 'cameras')  CameraView.load();
    if (section === 'video')    VideoView.load();
    if (section === 'radio')    RadioView.load();
    if (section === 'settings') SettingsView.render();
    if (section === 'devices')  auth.initDevicesPanel();
  },

  showMessage(text, type = 'info') {
    const el = document.getElementById('global-message');
    if (!el) return;
    if (!text) { el.hidden = true; return; }
    el.hidden    = false;
    el.className = `message message--${type}`;
    el.textContent = text;
  },

  showToast(text, type = 'success', duration = 3500) {
    const toasts = document.getElementById('toasts') || document.body;
    const toast  = document.createElement('div');
    toast.className   = `toast toast--${type}`;
    toast.textContent = text;
    toasts.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast--visible'));
    setTimeout(() => {
      toast.classList.remove('toast--visible');
      setTimeout(() => toast.remove(), 400);
    }, duration);
  },
};

const CameraView = {
  loaded: false,
  load() { if (this.loaded) return; this.loaded = true; CommandCenter.load(); },
};

const VideoView = {
  loaded: false,
  hls:    null,
  load() {
    if (this.loaded) return;
    this.loaded = true;
    const video = document.getElementById('nvisor-video');
    if (!video) return;
    const src = video.dataset.src;
    if (!src) return;

    if (Hls.isSupported()) {
      this.hls = new Hls();
      this.hls.loadSource(src);
      this.hls.attachMedia(video);
      this.hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = src;
      video.play().catch(() => {});
    }
  },
};

const RadioView = {
  loaded: false,
  load() { if (this.loaded) return; this.loaded = true; RadioPlayer.init(); },
};

const SettingsView = {
  render() {
    const container = document.getElementById('panel-settings');
    if (!container) return;
    const user    = auth?.user;
    const initial = (user?.display_name || '?')[0].toUpperCase();

    container.innerHTML = `
      <div class="settings-card settings-user-card">
        <div class="settings-avatar">${initial}</div>
        <div class="settings-user-info">
          <span class="settings-user-name">${user?.display_name || ''}</span>
          <span class="settings-user-email">${user?.email || ''}</span>
          <span class="settings-user-level">${user?.level_name || 'VIP'}</span>
        </div>
      </div>

      <div class="settings-section">
        <p class="settings-section__label">DISPOSITIVOS</p>
        <button class="settings-item" onclick="App.goTo('devices')">
          <span class="settings-item__icon">💻</span>
          <div class="settings-item__info">
            <span class="settings-item__title">Mis Dispositivos</span>
            <span class="settings-item__sub">Ver y gestionar tus dispositivos registrados</span>
          </div>
          <span class="settings-item__arrow">›</span>
        </button>
      </div>

      <div class="settings-section">
        <p class="settings-section__label">AYUDA</p>
        <a class="settings-item" href="https://kmultimedios.com/contacto/" target="_blank">
          <span class="settings-item__icon">💬</span>
          <div class="settings-item__info">
            <span class="settings-item__title">Soporte</span>
            <span class="settings-item__sub">Contactar al equipo de KMultimedios</span>
          </div>
          <span class="settings-item__arrow">›</span>
        </a>
        <a class="settings-item" href="https://kmultimedios.com" target="_blank">
          <span class="settings-item__icon">🌐</span>
          <div class="settings-item__info">
            <span class="settings-item__title">Sitio Web</span>
            <span class="settings-item__sub">kmultimedios.com</span>
          </div>
          <span class="settings-item__arrow">›</span>
        </a>
      </div>

      <div class="settings-section">
        <p class="settings-section__label">SESIÓN</p>
        <a class="settings-item settings-item--danger"
           href="https://kmultimedios.com/wp-login.php?action=logout"
           onclick="return confirm('¿Cerrar sesión?')">
          <span class="settings-item__icon">🚪</span>
          <div class="settings-item__info">
            <span class="settings-item__title">Cerrar Sesión</span>
            <span class="settings-item__sub">Salir de tu cuenta VIP</span>
          </div>
        </a>
      </div>

      <p class="settings-version">KMultimedios VIP Windows · v1.0</p>
    `;
  },
};

// ── Init ──────────────────────────────────────────────────────────────────────
let wa;
let auth;

document.addEventListener('DOMContentLoaded', async () => {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/vaderwin/sw.js', { scope: '/vaderwin/' })
      .then(r => console.log('[SW] scope:', r.scope))
      .catch(e => console.warn('[SW] error:', e));
  }

  wa   = new WebAuthnManager(API_BASE);
  auth = new AuthManager(wa);

  document.querySelectorAll('[data-section]').forEach(el => {
    el.addEventListener('click', () => App.goTo(el.dataset.section));
  });

  document.getElementById('register-btn')?.addEventListener('click',      () => auth.doRegister());
  document.getElementById('retry-btn')?.addEventListener('click',          () => auth.init());
  document.getElementById('verify-retry-btn')?.addEventListener('click',   () => auth.doVerify());

  document.getElementById('modal-confirm-btn')?.addEventListener('click', () => auth.confirmDelete());
  document.getElementById('modal-cancel-btn')?.addEventListener('click',  () => {
    document.getElementById('modal-overlay')?.classList.remove('modal--open');
    auth._pendingDelete = null;
  });
  document.getElementById('modal-overlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
      e.currentTarget.classList.remove('modal--open');
      auth._pendingDelete = null;
    }
  });

  document.getElementById('screen-login-required')
    ?.querySelectorAll('a[href*="wp-login"]')
    .forEach(link => {
      link.addEventListener('click', (e) => { e.preventDefault(); window.location.href = link.href; });
    });

  await auth.init();
});

document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('toasts')) {
    const t = document.createElement('div');
    t.id = 'toasts';
    document.body.appendChild(t);
  }
});
