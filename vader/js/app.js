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

    // Actualizar nav activo en pantalla principal
    if (id === 'main') {
      document.querySelectorAll('.bottom-nav__item').forEach(el => el.classList.remove('bottom-nav__item--active'));
      document.getElementById('nav-home')?.classList.add('bottom-nav__item--active');
    }
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
    // Inicializar el Command Center nativo
    initTabs();
    RadioPlayer.init();
    CommandCenter.load();
  },
};

// ── Content Manager (mantenido para compatibilidad) ───────────────────────────
const ContentManager = {
  async load() {
    const grid = document.getElementById('content-grid');
    if (!grid) return;

    grid.innerHTML = '<div class="content-skeleton"></div>'.repeat(4);

    try {
      const data  = await wa._fetch('/content');
      const items = data.items || [];

      if (!items.length) {
        grid.innerHTML = '<p class="content-empty">No hay contenido disponible aún.</p>';
        return;
      }

      grid.innerHTML = '';
      items.forEach(item => grid.appendChild(this._card(item)));

    } catch (err) {
      grid.innerHTML = `<p class="content-empty content-empty--error">Error cargando contenido: ${err.message}</p>`;
    }
  },

  _card(item) {
    const card  = document.createElement('article');
    card.className  = 'content-card';
    card.dataset.id = item.id;

    const thumb = item.thumbnail
      ? `<img src="${item.thumbnail}" alt="${item.title}" loading="lazy" class="content-card__img">`
      : '<div class="content-card__img content-card__img--placeholder"></div>';

    const date = item.date
      ? new Date(item.date).toLocaleDateString('es-MX', { day:'numeric', month:'short', year:'numeric' })
      : '';

    card.innerHTML = `
      ${thumb}
      <div class="content-card__body">
        <span class="content-card__type">${item.type === 'post' ? 'Artículo' : 'Página'}</span>
        <h3 class="content-card__title">${item.title}</h3>
        <p class="content-card__excerpt">${item.excerpt || ''}</p>
        <span class="content-card__date">${date}</span>
      </div>`;

    card.addEventListener('click', () => this._openItem(item.id));
    return card;
  },

  async _openItem(id) {
    const detail = document.getElementById('content-detail');
    if (!detail) return;

    App.showScreen('content-detail');
    detail.innerHTML = '<div class="loading-spinner" style="margin:60px auto"></div>';

    try {
      const item = await wa._fetch(`/content/${id}`);
      const date = item.date
        ? new Date(item.date).toLocaleDateString('es-MX', { day:'numeric', month:'long', year:'numeric' })
        : '';

      detail.innerHTML = `
        ${item.thumbnail ? `<img src="${item.thumbnail}" alt="${item.title}" class="detail__hero">` : ''}
        <div class="detail__content">
          <h1 class="detail__title">${item.title}</h1>
          <div class="detail__meta">
            ${item.author ? `<span>${item.author}</span>` : ''}
            ${date ? `<span>${date}</span>` : ''}
          </div>
          <div class="detail__body">${item.content || ''}</div>
        </div>`;

    } catch (err) {
      detail.innerHTML = `<div class="screen-inner screen-inner--centered">
        <p class="error-msg">Error: ${err.message}</p>
        <button class="btn btn--ghost" onclick="App.showScreen('main')">← Volver</button>
      </div>`;
    }
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

  // ── Botones de autenticación ─────────────────────────────────────────────
  document.getElementById('register-btn')?.addEventListener('click',    () => auth.doRegister());
  document.getElementById('retry-btn')?.addEventListener('click',        () => auth.init());
  document.getElementById('verify-retry-btn')?.addEventListener('click', () => auth.doVerify());

  // ── Navegación a panel de dispositivos ──────────────────────────────────
  function goToDevices() {
    App.showScreen('devices');
    // Actualizar nav activo
    document.querySelectorAll('#screen-main .bottom-nav__item').forEach(el => el.classList.remove('bottom-nav__item--active'));
    document.getElementById('nav-devices')?.classList.add('bottom-nav__item--active');
    auth.initDevicesPanel();
  }

  document.getElementById('nav-devices')?.addEventListener('click', goToDevices);
  document.getElementById('nav-devices-2')?.addEventListener('click', goToDevices);

  // ── Botones nav de pantalla principal ────────────────────────────────────
  document.getElementById('nav-home')?.addEventListener('click', () => App.showScreen('main'));

  // ── Botones volver desde panel dispositivos ──────────────────────────────
  document.getElementById('devices-back-btn')?.addEventListener('click',  () => App.showScreen('main'));
  document.getElementById('devices-nav-home')?.addEventListener('click',  () => App.showScreen('main'));

  // ── Modal de confirmación de eliminar ────────────────────────────────────
  document.getElementById('modal-confirm-btn')?.addEventListener('click', () => auth.confirmDelete());
  document.getElementById('modal-cancel-btn')?.addEventListener('click',  () => {
    const modal = document.getElementById('modal-overlay');
    if (modal) modal.classList.remove('modal--open');
    auth._pendingDelete = null;
  });
  // Cerrar modal al hacer clic fuera
  document.getElementById('modal-overlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
      e.currentTarget.classList.remove('modal--open');
      auth._pendingDelete = null;
    }
  });

  // ── Botón instalar ───────────────────────────────────────────────────────
  document.getElementById('install-btn')?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
      App.showToast('Abre el menú del navegador y selecciona "Instalar app" o "Añadir a inicio".', 'info', 5000);
      return;
    }
    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    if (outcome === 'accepted') App.showToast('Instalando app…', 'success');
    deferredInstallPrompt = null;
  });

  // ── Hash navigation ──────────────────────────────────────────────────────
  window.addEventListener('hashchange', handleHash);

  // ── Iniciar flujo ────────────────────────────────────────────────────────
  // Botón "Iniciar Sesión": forzar navegación en el mismo contexto
  // (en modo standalone PWA los <a> externos abren en otra ventana)
  document.getElementById('screen-login-required')
    ?.querySelectorAll('a[href*="wp-login"], a[href*="wp-admin"]')
    .forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = link.href;
      });
    });

  await auth.init();
  handleHash();
});

function handleHash() {
  if (!auth?.isReady) return;
  if (window.location.hash === '#dispositivos') {
    App.showScreen('devices');
    auth.initDevicesPanel();
  } else if (window.location.hash === '#contenido') {
    App.showScreen('main');
  }
}

// Contenedor de toasts
document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('toasts')) {
    const t = document.createElement('div');
    t.id = 'toasts';
    document.body.appendChild(t);
  }
});
