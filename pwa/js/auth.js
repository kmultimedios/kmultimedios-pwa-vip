/**
 * Auth Manager – KMultimedios VIP v2.0
 * Gestiona 2 ranuras: mobile + desktop (Windows Hello)
 */

class AuthManager {
  constructor(waManager) {
    this.wa              = waManager;
    this.user            = null;
    this.isReady         = false;
    this._pendingDelete  = null; // { deviceId, deviceType } para el modal
  }

  // ── Flujo principal ───────────────────────────────────────────────────────

  async init() {
    try {
      App.showScreen('loading');

      // 1. Estado del usuario
      const status = await this.wa.checkVipStatus();

      if (!status.is_logged_in) {
        App.showScreen('login-required');
        return;
      }
      if (!status.is_vip) {
        App.showScreen('not-vip');
        return;
      }

      this.user = status;
      this.updateUserUI();

      const dtype        = status.current_device_type;
      const hasThisSlot  = dtype === 'mobile' ? !!status.mobile_device : !!status.desktop_device;
      const slotBlocked  = dtype === 'mobile'  ? status.mobile_blocked  : status.desktop_blocked;

      if (!hasThisSlot) {
        // Verificar soporte WebAuthn
        const supported = await WebAuthnManager.isAvailable();
        if (!supported) {
          App.showScreen('not-supported');
          return;
        }

        // Cuota anual agotada para esta ranura
        if (slotBlocked) {
          const nextYear = new Date().getFullYear() + 1;
          const el = document.getElementById('blocked-message');
          if (el) {
            const slotLabel = dtype === 'mobile' ? 'móvil' : 'escritorio';
            el.textContent = `Has usado los ${status.max_replacements} reemplazos permitidos este año para tu ranura de ${slotLabel}.`;
          }
          const resetEl = document.getElementById('reset-date-label');
          if (resetEl) resetEl.textContent = `1 de enero de ${nextYear}`;
          App.showScreen('slot-blocked');
          return;
        }

        // Actualizar indicador de ranura en pantalla de registro
        DeviceUI.updateSlotIndicator(dtype);
        App.showScreen('register');
        return;
      }

      // Tiene dispositivo en esta ranura → verificar
      await this.doVerify();

    } catch (err) {
      console.error('[Auth] init error:', err);
      App.showScreen('error');
      App.showMessage(err.message || 'Error de conexión.', 'error');
    }
  }

  // ── Registro ──────────────────────────────────────────────────────────────

  async doRegister() {
    const btn       = document.getElementById('register-btn');
    const nameInput = document.getElementById('device-name-input');

    try {
      btn.disabled    = true;
      btn.textContent = 'Procesando…';
      App.showMessage('Sigue las instrucciones de tu dispositivo para la autenticación biométrica.', 'info');

      const deviceName = nameInput?.value?.trim() || '';
      await this.wa.registerDevice(deviceName);

      App.showMessage('¡Dispositivo registrado!', 'success');
      await this.doVerify();

    } catch (err) {
      console.error('[Auth] register error:', err);
      App.showMessage(err.message || 'Error al registrar dispositivo.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Registrar este dispositivo';
    }
  }

  // ── Verificación ──────────────────────────────────────────────────────────

  async doVerify() {
    try {
      App.showScreen('loading');
      App.showMessage('Verificando identidad biométrica…', 'info');

      const result = await this.wa.verifyDevice(this.user.user_id);

      // El servidor dijo que esta ranura no tiene dispositivo → redirigir a registro
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
      App.showScreen('main');
      this.isReady = true;

      // Cargar iframe de cámaras (solo después de autenticar)
      CameraView.load();

    } catch (err) {
      // Si el error es que no existe dispositivo en esta ranura, ir a registro
      if (err.message?.includes('no_device_for_slot') || err.message?.includes('no está registrado')) {
        DeviceUI.updateSlotIndicator(this.user?.current_device_type || 'mobile');
        App.showScreen('register');
        App.showMessage('', '');
        return;
      }
      console.error('[Auth] verify error:', err);
      App.showScreen('verify-failed');
      App.showMessage(err.message || 'Verificación fallida.', 'error');
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

      // Delegar eventos de los botones de eliminar
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

    const slotLabel  = deviceType === 'mobile' ? 'móvil' : 'escritorio';
    const usedCount  = deviceType === 'mobile' ? data.mobile_replacements_this_year : data.desktop_replacements_this_year;
    const maxCount   = data.max_replacements;
    const remaining  = Math.max(0, maxCount - usedCount - 1); // tras esta operación

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

    const modal = document.getElementById('modal-overlay');
    if (modal) modal.classList.remove('modal--open');

    const confirmBtn = document.getElementById('modal-confirm-btn');
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Eliminando…'; }

    try {
      const result = await this.wa.deleteMyDevice(deviceId);
      App.showToast(result.message || 'Dispositivo eliminado.', 'success', 4000);
      await this.initDevicesPanel(); // refrescar el panel
    } catch (err) {
      App.showToast(err.message || 'Error al eliminar dispositivo.', 'error', 5000);
    } finally {
      if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Sí, eliminar'; }
    }
  }

  // ── UI helpers ────────────────────────────────────────────────────────────

  updateUserUI() {
    const nameEls  = document.querySelectorAll('.js-user-name');
    const levelEls = document.querySelectorAll('.js-user-level');
    nameEls.forEach(el  => el.textContent = this.user?.display_name || '');
    levelEls.forEach(el => el.textContent = this.user?.level_name   || 'VIP');

    // Avatar inicial
    const avatars = document.querySelectorAll('.user-avatar');
    const initial = (this.user?.display_name || '?')[0].toUpperCase();
    avatars.forEach(el => el.textContent = initial);
  }

  // Exponer para el modal (llamado desde app.js)
  confirmDelete() { return this._confirmDelete(); }
}

window.AuthManager = AuthManager;
