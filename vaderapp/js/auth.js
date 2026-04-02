/**
 * Auth Manager – KMultimedios VIP Android (vaderapp)
 * Autenticación por fingerprint únicamente (sin WebAuthn/biometría nativa).
 */

class AuthManager {
  constructor(waManager) {
    this.wa             = waManager;
    this.user           = null;
    this.isReady        = false;
    this._pendingDelete = null;
  }

  // ── Flujo principal ────────────────────────────────────────────────────────

  async init() {
    try {
      App.showScreen('loading');

      // 1. Bootstrap (cookies WP, sin nonce previo)
      if (!this.wa.nonce) {
        const boot = await this.wa.getBootstrap();
        if (!boot.is_logged_in) { App.showScreen('login-required'); return; }
        if (!boot.is_vip)       { App.showScreen('not-vip');        return; }
      }

      // 2. Estado completo del usuario y sus dispositivos
      const status = await this.wa.checkVipStatus();
      if (!status.is_logged_in) { App.showScreen('login-required'); return; }
      if (!status.is_vip)       { App.showScreen('not-vip');        return; }

      this.user = status;
      this.updateUserUI();

      const dtype       = status.current_device_type;                               // 'mobile' en Android
      const hasThisSlot = dtype === 'mobile' ? !!status.mobile_device  : !!status.desktop_device;
      const slotBlocked = dtype === 'mobile' ?   status.mobile_blocked  :   status.desktop_blocked;

      // — Sin dispositivo registrado —
      if (!hasThisSlot) {
        // Intentar fingerprint por si hay desincronía de sesión
        const silentOk = await this.tryFingerprintLogin();
        if (silentOk) return;

        if (slotBlocked) {
          const nextYear = new Date().getFullYear() + 1;
          const el  = document.getElementById('blocked-message');
          const rel = document.getElementById('reset-date-label');
          const slotLabel = dtype === 'mobile' ? 'móvil' : 'escritorio';
          if (el)  el.textContent  = `Has usado los ${status.max_replacements} reemplazos permitidos este año para tu ranura de ${slotLabel}.`;
          if (rel) rel.textContent = `1 de enero de ${nextYear}`;
          App.showScreen('slot-blocked');
          return;
        }

        DeviceUI.updateSlotIndicator(dtype);
        App.showScreen('register');
        return;
      }

      // — Dispositivo registrado → login silencioso por fingerprint —
      const silentOk = await this.tryFingerprintLogin();
      if (!silentOk) {
        // Fingerprint no coincide (dispositivo diferente o localStorage limpio)
        App.showScreen('verify-failed');
      }

    } catch (err) {
      console.error('[Auth] init error:', err);
      App.showScreen('error');
      App.showMessage(err.message || 'Error de conexión.', 'error');
    }
  }

  // ── Login silencioso por fingerprint ──────────────────────────────────────

  async tryFingerprintLogin() {
    try {
      const fp = await DeviceFingerprint.generate();
      DeviceFingerprint.save(fp);

      const form = new FormData();
      form.append('action',      'pwa_fingerprint_login');
      form.append('user_id',     this.user.user_id);
      form.append('fingerprint', fp);

      const res = await fetch(this.wa.ajaxUrl, { method: 'POST', credentials: 'include', body: form });
      if (!res.ok) return false;

      const data = await res.json();
      if (!data.success) return false;

      // Nonce fresco con la nueva sesión
      const freshBoot = await this.wa.getBootstrap();
      if (freshBoot.nonce) this.wa.nonce = freshBoot.nonce;

      this.user.display_name = data.data?.display_name || this.user.display_name;
      this.user.level_name   = data.data?.level_name   || this.user.level_name;
      this.updateUserUI();
      this.isReady = true;
      App.goTo('home');
      return true;

    } catch {
      return false;
    }
  }

  // ── Registro (fingerprint únicamente) ─────────────────────────────────────

  async doRegister() {
    const btn       = document.getElementById('register-btn');
    const nameInput = document.getElementById('device-name-input');

    try {
      btn.disabled    = true;
      btn.textContent = 'Registrando…';

      const deviceName = nameInput?.value?.trim() || '';
      const fp         = await DeviceFingerprint.generate();
      DeviceFingerprint.save(fp);

      const form = new FormData();
      form.append('action',      'pwa_register_no_biometric');
      form.append('fingerprint', fp);
      form.append('device_name', deviceName);
      form.append('nonce',       this.wa.nonce || '');

      const res  = await fetch(this.wa.ajaxUrl, { method: 'POST', credentials: 'include', body: form });
      const data = await res.json();

      if (data.success) {
        // Actualizar nonce del servidor
        if (data.data?.nonce) this.wa.nonce = data.data.nonce;
        this.user.display_name = data.data?.display_name || this.user.display_name;
        this.user.level_name   = data.data?.level_name   || this.user.level_name;
        this.updateUserUI();
        this.isReady = true;
        App.showToast('Dispositivo registrado.', 'success');
        App.goTo('home');
      } else {
        throw new Error(data.data?.message || 'Error al registrar');
      }

    } catch (err) {
      App.showToast(err.message || 'Error al registrar dispositivo.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Registrar este dispositivo';
    }
  }

  // ── Re-registrar desde pantalla verify-failed ──────────────────────────────

  async doReRegister() {
    // Verificar si la ranura está bloqueada antes de mostrar la pantalla de registro
    try {
      App.showScreen('loading');
      const status = await this.wa.checkVipStatus();
      this.user = status;
      this.updateUserUI();

      const dtype       = status.current_device_type;
      const slotBlocked = dtype === 'mobile' ? status.mobile_blocked : status.desktop_blocked;

      if (slotBlocked) {
        const nextYear  = new Date().getFullYear() + 1;
        const el  = document.getElementById('blocked-message');
        const rel = document.getElementById('reset-date-label');
        const slotLabel = dtype === 'mobile' ? 'móvil' : 'escritorio';
        if (el)  el.textContent  = `Has usado los ${status.max_replacements} reemplazos permitidos este año para tu ranura de ${slotLabel}.`;
        if (rel) rel.textContent = `1 de enero de ${nextYear}`;
        App.showScreen('slot-blocked');
        return;
      }

      DeviceUI.updateSlotIndicator(dtype);
      App.showScreen('register');

    } catch (err) {
      App.showScreen('error');
      App.showMessage(err.message || 'Error de conexión.', 'error');
    }
  }

  // ── Panel de dispositivos ─────────────────────────────────────────────────

  async initDevicesPanel() {
    const container = document.getElementById('devices-panel-content');
    if (container) container.innerHTML = '<div class="loading-spinner" style="margin:40px auto"></div>';

    try {
      const data = await this.wa.getMyDevices();
      DeviceUI.renderDevicesPanel(data, (deviceId, deviceType) => {
        this._showDeleteModal(deviceId, deviceType, data);
      });

      document.querySelectorAll('[data-device-id]').forEach(btn => {
        btn.addEventListener('click', () => {
          const id   = parseInt(btn.dataset.deviceId);
          const type = btn.dataset.deviceType;
          this._showDeleteModal(id, type, data);
        });
      });

    } catch (err) {
      if (container) {
        container.innerHTML = `<div class="screen-inner screen-inner--centered">
          <p class="error-msg">Error cargando dispositivos: ${err.message}</p>
          <button class="btn btn--ghost" onclick="auth.initDevicesPanel()">Reintentar</button>
        </div>`;
      }
    }
  }

  _showDeleteModal(deviceId, deviceType, data) {
    this._pendingDelete = { deviceId, deviceType };

    const slotLabel = deviceType === 'mobile' ? 'móvil' : 'escritorio';
    const usedCount = deviceType === 'mobile' ? data.mobile_replacements_this_year : data.desktop_replacements_this_year;
    const maxCount  = data.max_replacements;
    const remaining = Math.max(0, maxCount - usedCount - 1);

    const desc = document.getElementById('modal-desc');
    if (desc) {
      desc.innerHTML = `Esto contará como <strong>1 reemplazo</strong> de tu cuota anual para la ranura <strong>${slotLabel}</strong>.<br><br>
        Usados: <strong>${usedCount}/${maxCount}</strong> — Te quedarán: <strong>${remaining}</strong> reemplazos este año.<br><br>
        La ranura quedará libre de inmediato para registrar un nuevo dispositivo.`;
    }

    const overlay = document.getElementById('modal-overlay');
    if (overlay) overlay.classList.add('modal--open');
  }

  async _confirmDelete() {
    if (!this._pendingDelete) return;

    const { deviceId, deviceType } = this._pendingDelete;
    this._pendingDelete = null;

    const modal      = document.getElementById('modal-overlay');
    const confirmBtn = document.getElementById('modal-confirm-btn');
    if (modal)      modal.classList.remove('modal--open');
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Eliminando…'; }

    try {
      const result = await this.wa.deleteMyDevice(deviceId);
      App.showToast(result.message || 'Dispositivo eliminado.', 'success', 4000);
      await this.initDevicesPanel();
    } catch (err) {
      App.showToast(err.message || 'Error al eliminar dispositivo.', 'error', 5000);
    } finally {
      if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Sí, eliminar'; }
    }
  }

  // ── UI helpers ─────────────────────────────────────────────────────────────

  updateUserUI() {
    const nameEls  = document.querySelectorAll('.js-user-name');
    const levelEls = document.querySelectorAll('.js-user-level');
    nameEls.forEach(el  => el.textContent = this.user?.display_name || '');
    levelEls.forEach(el => el.textContent = this.user?.level_name   || 'VIP');

    const initial = (this.user?.display_name || '?')[0].toUpperCase();
    document.querySelectorAll('.user-avatar').forEach(el => el.textContent = initial);
  }

  confirmDelete() { return this._confirmDelete(); }
}

window.AuthManager = AuthManager;
