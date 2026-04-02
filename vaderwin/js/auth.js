/**
 * Auth Manager – KMultimedios VIP Windows (vaderwin)
 * Windows Hello (WebAuthn) + fingerprint fallback.
 */

class AuthManager {
  constructor(waManager) {
    this.wa             = waManager;
    this.user           = null;
    this.isReady        = false;
    this._pendingDelete = null;
  }

  async init() {
    try {
      App.showScreen('loading');

      if (!this.wa.nonce) {
        const boot = await this.wa.getBootstrap();
        if (!boot.is_logged_in) { App.showScreen('login-required'); return; }
        if (!boot.is_vip)       { App.showScreen('not-vip');        return; }
      }

      const status = await this.wa.checkVipStatus();
      if (!status.is_logged_in) { App.showScreen('login-required'); return; }
      if (!status.is_vip)       { App.showScreen('not-vip');        return; }

      this.user = status;
      this.updateUserUI();

      const dtype       = status.current_device_type; // 'desktop' en Windows
      const hasThisSlot = dtype === 'mobile' ? !!status.mobile_device  : !!status.desktop_device;
      const slotBlocked = dtype === 'mobile' ?   status.mobile_blocked  :   status.desktop_blocked;

      if (!hasThisSlot) {
        const silentOk = await this.tryFingerprintLogin();
        if (silentOk) return;

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
        return;
      }

      // Intentar fingerprint primero, luego Windows Hello
      const silentOk = await this.tryFingerprintLogin();
      if (!silentOk) await this.doVerify();

    } catch (err) {
      console.error('[Auth] init error:', err);
      App.showScreen('error');
      App.showMessage(err.message || 'Error de conexión.', 'error');
    }
  }

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

      const freshBoot = await this.wa.getBootstrap();
      if (freshBoot.nonce) this.wa.nonce = freshBoot.nonce;

      this.user.display_name = data.data?.display_name || this.user.display_name;
      this.user.level_name   = data.data?.level_name   || this.user.level_name;
      this.updateUserUI();
      this.isReady = true;
      App.goTo('home');
      return true;
    } catch { return false; }
  }

  async doRegister() {
    const btn       = document.getElementById('register-btn');
    const nameInput = document.getElementById('device-name-input');

    try {
      btn.disabled    = true;
      btn.textContent = 'Procesando…';
      App.showMessage('Sigue las instrucciones de Windows Hello para autenticarte.', 'info');

      const deviceName = nameInput?.value?.trim() || '';
      await this.wa.registerDevice(deviceName);

      App.showMessage('¡Dispositivo registrado!', 'success');
      const freshBoot = await this.wa.getBootstrap();
      if (freshBoot.nonce) this.wa.nonce = freshBoot.nonce;
      this.isReady = true;
      App.goTo('home');

    } catch (err) {
      if (err.message?.toLowerCase().includes('ya tiene un dispositivo') ||
          err.message?.toLowerCase().includes('slot_full')) {
        App.showMessage('Dispositivo ya registrado. Verificando…', 'info');
        await this.doVerify();
        return;
      }
      // Si WebAuthn no está disponible, caer en registro por fingerprint
      if (err.message?.includes('no soporta WebAuthn') || err.message?.includes('NotSupportedError')) {
        await this.doRegisterFingerprint(deviceName);
        return;
      }
      App.showMessage(err.message || 'Error al registrar dispositivo.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Registrar con Windows Hello';
    }
  }

  async doRegisterFingerprint(deviceName = '') {
    try {
      const fp = await DeviceFingerprint.generate();
      DeviceFingerprint.save(fp);

      const form = new FormData();
      form.append('action',      'pwa_register_no_biometric');
      form.append('fingerprint', fp);
      form.append('device_name', deviceName || 'Windows PC');
      form.append('nonce',       this.wa.nonce || '');

      const res  = await fetch(this.wa.ajaxUrl, { method: 'POST', credentials: 'include', body: form });
      const data = await res.json();

      if (data.success) {
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
      App.showToast(err.message || 'Error al registrar.', 'error');
      const btn = document.getElementById('register-btn');
      if (btn) { btn.disabled = false; btn.textContent = 'Registrar con Windows Hello'; }
    }
  }

  async doVerify() {
    try {
      App.showScreen('loading');
      App.showMessage('Verificando con Windows Hello…', 'info');

      const result = await this.wa.verifyDevice(this.user.user_id);

      if (result?.error === 'no_device_for_slot') {
        DeviceUI.updateSlotIndicator(this.user.current_device_type);
        App.showScreen('register');
        App.showMessage('', '');
        return;
      }

      this.user.display_name = result.display_name;
      this.user.level_name   = result.level_name;
      this.updateUserUI();
      App.showMessage('', '');
      this.isReady = true;
      App.goTo('home');

    } catch (err) {
      if (err.message?.includes('no_device_for_slot') || err.message?.includes('no está registrado')) {
        DeviceUI.updateSlotIndicator(this.user?.current_device_type || 'desktop');
        App.showScreen('register');
        App.showMessage('', '');
        return;
      }
      App.showScreen('verify-failed');
      App.showMessage(err.message || 'Verificación fallida.', 'error');
    }
  }

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
        Usados: <strong>${usedCount}/${maxCount}</strong> — Te quedarán: <strong>${remaining}</strong> reemplazos este año.`;
    }

    document.getElementById('modal-overlay')?.classList.add('modal--open');
  }

  async _confirmDelete() {
    if (!this._pendingDelete) return;
    const { deviceId } = this._pendingDelete;
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
      App.showToast(err.message || 'Error al eliminar.', 'error', 5000);
    } finally {
      if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Sí, eliminar'; }
    }
  }

  updateUserUI() {
    document.querySelectorAll('.js-user-name').forEach(el  => el.textContent = this.user?.display_name || '');
    document.querySelectorAll('.js-user-level').forEach(el => el.textContent = this.user?.level_name   || 'VIP');
    const initial = (this.user?.display_name || '?')[0].toUpperCase();
    document.querySelectorAll('.user-avatar').forEach(el => el.textContent = initial);
  }

  confirmDelete() { return this._confirmDelete(); }
}

window.AuthManager = AuthManager;
