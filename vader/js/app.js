/**
 * App Principal – KMultimedios VIP PWA v2.0
 */

const API_BASE = '/wp-json/pwa/v1';

// ── App Controller ────────────────────────────────────────────────────────────
const App = {
  currentScreen: null,

  showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => {
      s.classList.toggle('active', s.id === `screen-${id}`);
    });
    this.currentScreen = id;
    window.scrollTo(0, 0);
  },

  // Navegar entre secciones principales con carga lazy
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

// ── Camera View ───────────────────────────────────────────────────────────────
const CameraView = {
  loaded: false,
  load() {
    if (this.loaded) return;
    this.loaded = true;
    CommandCenter.load();
  },
};

// ── Video View ────────────────────────────────────────────────────────────────
const VideoView = {
  loaded: false,
  load() {
    if (this.loaded) return;
    this.loaded = true;
    // Cargar iframe de Nvisor solo cuando el usuario entra por primera vez
    const iframe = document.getElementById('nvisor-iframe');
    if (iframe && iframe.src === 'about:blank') {
      iframe.src = iframe.dataset.src || '';
    }
  },
};

// ── Radio View ────────────────────────────────────────────────────────────────
const RadioView = {
  loaded: false,
  load() {
    if (this.loaded) return;
    this.loaded = true;
    RadioPlayer.init();
  },
};

// ── Settings View ─────────────────────────────────────────────────────────────
const SettingsView = {
  render() {
    const container = document.getElementById('panel-settings');
    if (!container) return;
    const user = auth?.user;
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
          <span class="settings-item__icon">📲</span>
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

      <p class="settings-version">KMultimedios VIP · v2.1</p>
    `;
  },
};

// ── PWA Install ───────────────────────────────────────────────────────────────
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredInstallPrompt = e;
  const banner = document.getElementById('install-banner');
  if (banner) banner.hidden = false;
});

window.addEventListener('appinstalled', () => {
  deferredInstallPrompt = null;
  const banner = document.getElementById('install-banner');
  if (banner) banner.hidden = true;
  App.showToast('¡App instalada correctamente!', 'success');
});

// ── iOS Install Banner ────────────────────────────────────────────────────────
(function () {
  const isIOS        = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.navigator.standalone === true;
  const isSafari     = !/crios|fxios|opios|mercury/i.test(navigator.userAgent);

  if (!isIOS || isStandalone || !isSafari) return;
  if (sessionStorage.getItem('ios-banner-dismissed')) return;

  setTimeout(() => {
    const banner = document.getElementById('ios-install-banner');
    if (banner) banner.hidden = false;
  }, 2500);

  document.getElementById('ios-install-close')?.addEventListener('click', () => {
    const banner = document.getElementById('ios-install-banner');
    if (banner) banner.hidden = true;
    sessionStorage.setItem('ios-banner-dismissed', '1');
  });
}());

// ── Init ──────────────────────────────────────────────────────────────────────
let wa;
let auth;

document.addEventListener('DOMContentLoaded', async () => {
  // Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/vader/sw.js', { scope: '/vader/' })
      .then(r => console.log('[SW] scope:', r.scope))
      .catch(e => console.warn('[SW] error:', e));
  }

  wa   = new WebAuthnManager(API_BASE);
  auth = new AuthManager(wa);

  // ── Navegación global por data-section ──────────────────────────────────
  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-section]');
    if (btn && auth?.isReady) App.goTo(btn.dataset.section);
  });

  // ── Botones de autenticación ─────────────────────────────────────────────
  document.getElementById('register-btn')?.addEventListener('click',    () => auth.doRegister());
  document.getElementById('retry-btn')?.addEventListener('click',        () => auth.init());
  document.getElementById('verify-retry-btn')?.addEventListener('click', () => auth.doVerify());

  // ── Modal confirmación eliminar ──────────────────────────────────────────
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

  // ── Botón instalar ───────────────────────────────────────────────────────
  document.getElementById('install-btn')?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
      App.showToast('Abre el menú del navegador y selecciona "Instalar app".', 'info', 5000);
      return;
    }
    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    if (outcome === 'accepted') App.showToast('Instalando app…', 'success');
    deferredInstallPrompt = null;
  });

  // ── Botón Iniciar Sesión: forzar navegación en standalone ───────────────
  document.getElementById('screen-login-required')
    ?.querySelectorAll('a[href*="wp-login"]')
    .forEach(link => {
      link.addEventListener('click', (e) => { e.preventDefault(); window.location.href = link.href; });
    });

  await auth.init();
});

// Contenedor de toasts
document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('toasts')) {
    const t = document.createElement('div');
    t.id = 'toasts';
    document.body.appendChild(t);
  }
});
