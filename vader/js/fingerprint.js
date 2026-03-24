/**
 * Device Fingerprint – KMultimedios VIP
 * Genera una huella única del dispositivo sin cookies.
 * Se usa como respaldo si el usuario borró sus datos:
 *   - Mismo dispositivo → acceso silencioso, sin crédito
 *   - Dispositivo nuevo  → requiere registro normal
 */

const DeviceFingerprint = {
  _cache: null,

  async generate() {
    if (this._cache) return this._cache;

    const parts = [];

    // ── Canvas fingerprint ──────────────────────────────────────────────────
    try {
      const c   = document.createElement('canvas');
      c.width   = 220; c.height = 50;
      const ctx = c.getContext('2d');
      ctx.textBaseline = 'top';
      ctx.fillStyle    = '#f60';
      ctx.fillRect(120, 1, 60, 22);
      ctx.fillStyle = '#069';
      ctx.font      = '13px Arial,sans-serif';
      ctx.fillText('KMVIP\u2764fp', 2, 15);
      ctx.fillStyle = 'rgba(102,204,0,.75)';
      ctx.fillText('KMVIP\u2764fp', 4, 17);
      // Solo usamos el final del dataURL (más estable entre sesiones)
      parts.push('cv:' + c.toDataURL().slice(-80));
    } catch (e) { parts.push('cv:x'); }

    // ── WebGL – GPU vendor + renderer ───────────────────────────────────────
    try {
      const gl  = document.createElement('canvas').getContext('webgl');
      const ext = gl && gl.getExtension('WEBGL_debug_renderer_info');
      if (ext) {
        parts.push('gv:' + gl.getParameter(ext.UNMASKED_VENDOR_WEBGL));
        parts.push('gr:' + gl.getParameter(ext.UNMASKED_RENDERER_WEBGL));
      }
    } catch (e) { parts.push('gl:x'); }

    // ── Pantalla ────────────────────────────────────────────────────────────
    parts.push('sw:' + screen.width);
    parts.push('sh:' + screen.height);
    parts.push('sd:' + screen.colorDepth);
    parts.push('dpr:' + (window.devicePixelRatio || 1));

    // ── Sistema ─────────────────────────────────────────────────────────────
    parts.push('tz:' + (Intl.DateTimeFormat().resolvedOptions().timeZone || 'unk'));
    parts.push('hw:' + (navigator.hardwareConcurrency || 0));
    parts.push('dm:' + (navigator.deviceMemory || 0));
    parts.push('lg:' + navigator.language);
    parts.push('pl:' + navigator.platform);
    parts.push('tc:' + ('ontouchstart' in window ? 1 : 0));

    // ── Audio fingerprint (ligero) ──────────────────────────────────────────
    try {
      const ac  = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 44100 });
      const osc = ac.createOscillator();
      const ana = ac.createAnalyser();
      const gain = ac.createGain();
      gain.gain.value = 0;
      osc.connect(ana);
      ana.connect(gain);
      gain.connect(ac.destination);
      osc.start(0);
      const buf = new Float32Array(ana.frequencyBinCount);
      ana.getFloatFrequencyData(buf);
      osc.stop();
      ac.close();
      parts.push('au:' + buf.slice(0, 5).join(','));
    } catch (e) { parts.push('au:x'); }

    this._cache = this._hash(parts.join('||'));
    return this._cache;
  },

  // FNV-1a 32-bit hash → hex
  _hash(str) {
    let h = 0x811c9dc5;
    for (let i = 0; i < str.length; i++) {
      h ^= str.charCodeAt(i);
      h  = Math.imul(h, 0x01000193) >>> 0;
    }
    return h.toString(16).padStart(8, '0');
  },

  // Persistencia local (respaldo)
  save(fp) {
    try { localStorage.setItem('_kmfp', fp); } catch (e) {}
    try { sessionStorage.setItem('_kmfp', fp); } catch (e) {}
  },

  load() {
    try {
      return localStorage.getItem('_kmfp') || sessionStorage.getItem('_kmfp') || null;
    } catch (e) { return null; }
  },
};

window.DeviceFingerprint = DeviceFingerprint;
