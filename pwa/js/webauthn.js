/**
 * WebAuthn Manager – KMultimedios VIP v2.0
 * Soporta 2 ranuras: mobile y desktop (Windows Hello incluido)
 */

class WebAuthnManager {
  constructor(apiBase) {
    this.apiBase = apiBase || '/wp-json/pwa/v1';
    this.nonce   = null;
  }

  // ── Helpers base64url ─────────────────────────────────────────────────────

  static base64urlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padded  = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=');
    const binary  = atob(padded);
    return Uint8Array.from(binary, (c) => c.charCodeAt(0));
  }

  static bufferToBase64url(buffer) {
    const bytes  = new Uint8Array(buffer);
    let   binary = '';
    for (const b of bytes) binary += String.fromCharCode(b);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
  }

  // ── API helper ────────────────────────────────────────────────────────────

  async _fetch(endpoint, options = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (this.nonce) headers['X-WP-Nonce'] = this.nonce;

    const res = await fetch(`${this.apiBase}${endpoint}`, {
      credentials: 'include',
      headers,
      ...options,
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || `Error ${res.status}`);
    return data;
  }

  // ── Estado VIP ────────────────────────────────────────────────────────────

  async checkVipStatus() {
    const data = await this._fetch('/check-vip');
    if (data.nonce) this.nonce = data.nonce;
    return data;
  }

  // ── Registro de dispositivo ───────────────────────────────────────────────

  async registerDevice(deviceName = '') {
    if (!window.PublicKeyCredential) {
      throw new Error('Tu navegador no soporta WebAuthn. Usa Chrome, Edge, Safari o Firefox actualizado.');
    }

    // 1. Obtener challenge y estado de la ranura
    const challengeData = await this._fetch('/challenge');

    // Ranura ocupada → el usuario debe eliminar primero desde el panel
    if (!challengeData.slot_available) {
      throw new Error('Esta ranura ya tiene un dispositivo registrado. Ve a "Mis Dispositivos" para gestionarlo.');
    }

    // Cuota anual agotada
    if (challengeData.is_blocked) {
      const nextYear = new Date().getFullYear() + 1;
      throw new Error(`Has alcanzado el límite de 2 reemplazos anuales para este tipo de dispositivo. Disponible el 1 de enero de ${nextYear}.`);
    }

    // 2. Actualizar el indicador de ranura en la UI
    DeviceUI.updateSlotIndicator(challengeData.device_type);

    // 3. Opciones WebAuthn
    const userEmail = document.querySelector('meta[name="user-email"]')?.content || 'usuario@km.com';
    const userName  = document.querySelector('meta[name="user-name"]')?.content  || 'Usuario VIP';

    const publicKeyOptions = {
      challenge: WebAuthnManager.base64urlToBuffer(challengeData.challenge),
      rp: {
        name: 'KMultimedios VIP',
        id:   challengeData.rp_id,
      },
      user: {
        id:          new TextEncoder().encode(String(challengeData.user_id)),
        name:        userEmail,
        displayName: userName,
      },
      pubKeyCredParams: [
        { alg: -7,   type: 'public-key' }, // ES256 – Android, Mac, iPhone
        { alg: -257, type: 'public-key' }, // RS256 – Windows Hello
      ],
      authenticatorSelection: {
        authenticatorAttachment: 'platform',  // Solo autenticador del propio dispositivo
        userVerification:        'required',  // Biometría/PIN obligatorio
        residentKey:             'preferred',
      },
      timeout:     60000,
      attestation: 'none',
    };

    // 4. Crear credencial
    let credential;
    try {
      credential = await navigator.credentials.create({ publicKey: publicKeyOptions });
    } catch (err) {
      if (err.name === 'NotAllowedError')   throw new Error('Autenticación cancelada. Intenta de nuevo.');
      if (err.name === 'NotSupportedError') throw new Error('Este dispositivo no soporta autenticación biométrica o Windows Hello.');
      throw new Error(`Error de autenticación: ${err.message}`);
    }

    // 5. Enviar al servidor
    const payload = {
      device_name: deviceName,
      response: {
        id:   credential.id,
        type: credential.type,
        clientDataJSON:    WebAuthnManager.bufferToBase64url(credential.response.clientDataJSON),
        attestationObject: WebAuthnManager.bufferToBase64url(credential.response.attestationObject),
      },
    };

    return await this._fetch('/register-device', { method: 'POST', body: JSON.stringify(payload) });
  }

  // ── Verificación (login con biometría) ────────────────────────────────────

  async verifyDevice(userId) {
    if (!window.PublicKeyCredential) {
      throw new Error('Tu navegador no soporta WebAuthn.');
    }

    const challengeData = await this._fetch('/challenge');

    const publicKeyOptions = {
      challenge:        WebAuthnManager.base64urlToBuffer(challengeData.challenge),
      rpId:             challengeData.rp_id,
      userVerification: 'required',
      timeout:          60000,
    };

    let assertion;
    try {
      assertion = await navigator.credentials.get({ publicKey: publicKeyOptions });
    } catch (err) {
      if (err.name === 'NotAllowedError') throw new Error('Autenticación cancelada. Intenta de nuevo.');
      throw new Error(`Error de verificación: ${err.message}`);
    }

    const payload = {
      user_id: userId,
      response: {
        id:   assertion.id,
        type: assertion.type,
        clientDataJSON:    WebAuthnManager.bufferToBase64url(assertion.response.clientDataJSON),
        authenticatorData: WebAuthnManager.bufferToBase64url(assertion.response.authenticatorData),
        signature:         WebAuthnManager.bufferToBase64url(assertion.response.signature),
        userHandle:        assertion.response.userHandle
          ? WebAuthnManager.bufferToBase64url(assertion.response.userHandle)
          : null,
      },
    };

    const result = await this._fetch('/verify-device', { method: 'POST', body: JSON.stringify(payload) });
    if (result.nonce) this.nonce = result.nonce;
    return result;
  }

  // ── Panel: obtener mis dispositivos ──────────────────────────────────────

  async getMyDevices() {
    return await this._fetch('/my-devices');
  }

  // ── Panel: eliminar mi dispositivo ────────────────────────────────────────

  async deleteMyDevice(deviceId) {
    return await this._fetch('/delete-my-device', {
      method: 'POST',
      body:   JSON.stringify({ device_id: deviceId }),
    });
  }

  // ── Disponibilidad ────────────────────────────────────────────────────────

  static async isAvailable() {
    if (!window.PublicKeyCredential) return false;
    try {
      return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch {
      return false;
    }
  }
}

// ── Helper UI para ranuras ────────────────────────────────────────────────────
const DeviceUI = {
  updateSlotIndicator(deviceType) {
    const icon  = document.getElementById('slot-icon');
    const label = document.getElementById('slot-label');
    const ricon = document.getElementById('register-icon');
    const desc  = document.getElementById('register-desc');
    const indicator = document.getElementById('slot-indicator');

    if (!indicator) return;

    if (deviceType === 'mobile') {
      if (icon)  icon.textContent  = '📱';
      if (label) label.textContent = 'Registrando: Móvil';
      if (ricon) ricon.textContent = '📱';
      if (desc)  desc.innerHTML = 'Puedes tener <strong>1 dispositivo móvil</strong> y <strong>1 escritorio/PC</strong>. Confirma con tu huella o Face ID.';
    } else {
      if (icon)  icon.textContent  = '💻';
      if (label) label.textContent = 'Registrando: Escritorio / PC';
      if (ricon) ricon.textContent = '💻';
      if (desc)  desc.innerHTML = 'Puedes tener <strong>1 dispositivo móvil</strong> y <strong>1 escritorio/PC</strong>. Confirma con Windows Hello, Touch ID o PIN.';
    }
  },

  renderDevicesPanel(data, onDelete) {
    const container = document.getElementById('devices-panel-content');
    if (!container) return;

    const year = data.year || new Date().getFullYear();

    container.innerHTML = `
      <div class="devices-panel">

        <p class="devices-intro">
          Puedes tener <strong>1 dispositivo móvil</strong> y <strong>1 escritorio/PC</strong>.<br>
          Máximo <strong>${data.max_replacements} reemplazos</strong> por tipo de dispositivo al año.
        </p>

        <!-- Ranura Móvil -->
        ${this._slotCard('mobile', '📱', 'Dispositivo Móvil', data.mobile_device, data.mobile_replacements_this_year, data.max_replacements, data.mobile_blocked, data.reset_date, data.current_device_type, onDelete)}

        <!-- Ranura Escritorio / Windows -->
        ${this._slotCard('desktop', '💻', 'Escritorio / PC (Windows, Mac)', data.desktop_device, data.desktop_replacements_this_year, data.max_replacements, data.desktop_blocked, data.reset_date, data.current_device_type, onDelete)}

        <div class="devices-info-box">
          <p>ℹ️ Al eliminar un dispositivo, la ranura queda libre de inmediato. La próxima vez que abras la app en el nuevo dispositivo, se registrará automáticamente.</p>
        </div>

      </div>
    `;
  },

  _slotCard(type, emoji, label, device, usedCount, maxCount, blocked, resetDate, currentType, onDelete) {
    const isCurrentDevice = type === currentType;
    const currentBadge = isCurrentDevice ? '<span class="slot-badge slot-badge--current">Este dispositivo</span>' : '';
    const quota = `${usedCount}/${maxCount}`;
    const quotaClass = usedCount >= maxCount ? 'quota--full' : usedCount > 0 ? 'quota--partial' : 'quota--empty';

    if (device) {
      const regDate   = new Date(device.registered_date).toLocaleDateString('es-MX', { day:'numeric', month:'short', year:'numeric' });
      const lastAccess = new Date(device.last_access).toLocaleDateString('es-MX', { day:'numeric', month:'short', year:'numeric' });

      const deleteSection = blocked
        ? `<div class="blocked-notice blocked-notice--sm">
             🔒 Límite anual alcanzado. Disponible el <strong>${new Date(resetDate).toLocaleDateString('es-MX', { day:'numeric', month:'long', year:'numeric' })}</strong>
           </div>`
        : `<button class="btn btn--danger btn--sm btn--full" data-device-id="${device.id}" data-device-type="${type}">
             🗑️ Eliminar y registrar otro
           </button>`;

      return `
        <div class="device-slot-card device-slot-card--active">
          <div class="device-slot-card__header">
            <span class="slot-type-icon">${emoji}</span>
            <div class="slot-type-info">
              <span class="slot-type-label">${label}</span>
              <span class="slot-badge slot-badge--active">Activo</span>
              ${currentBadge}
            </div>
          </div>
          <div class="device-slot-card__info">
            <p class="device-name">📍 ${device.device_name || 'Dispositivo sin nombre'}</p>
            <p class="device-meta">Registrado: ${regDate}</p>
            <p class="device-meta">Último acceso: ${lastAccess}</p>
          </div>
          <div class="quota-section">
            <span class="quota-label">Reemplazos usados este año:</span>
            <div class="quota-bar ${quotaClass}">
              <div class="quota-bar__fill" style="width:${(usedCount/maxCount)*100}%"></div>
            </div>
            <span class="quota-count">${quota}</span>
          </div>
          ${deleteSection}
        </div>`;
    } else {
      const registerHint = isCurrentDevice
        ? `<p class="slot-empty-hint">👆 Vuelve atrás y registra este dispositivo ahora.</p>`
        : `<p class="slot-empty-hint">Abre la app en tu ${type === 'mobile' ? 'teléfono' : 'PC/escritorio'} para registrarlo automáticamente.</p>`;

      return `
        <div class="device-slot-card device-slot-card--empty">
          <div class="device-slot-card__header">
            <span class="slot-type-icon">${emoji}</span>
            <div class="slot-type-info">
              <span class="slot-type-label">${label}</span>
              <span class="slot-badge slot-badge--empty">Ranura libre</span>
            </div>
          </div>
          <div class="device-slot-card__info">
            <p class="device-meta">No hay dispositivo registrado en esta ranura.</p>
          </div>
          <div class="quota-section">
            <span class="quota-label">Reemplazos usados este año:</span>
            <div class="quota-bar ${quotaClass}">
              <div class="quota-bar__fill" style="width:${(usedCount/maxCount)*100}%"></div>
            </div>
            <span class="quota-count">${quota}</span>
          </div>
          ${blocked
            ? `<div class="blocked-notice blocked-notice--sm">🔒 Límite anual alcanzado. Disponible el <strong>${new Date(resetDate).toLocaleDateString('es-MX', {day:'numeric',month:'long',year:'numeric'})}</strong></div>`
            : registerHint}
        </div>`;
    }
  },
};

window.WebAuthnManager = WebAuthnManager;
window.DeviceUI        = DeviceUI;
