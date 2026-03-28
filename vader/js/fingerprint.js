/**
 * Device Fingerprint – KMultimedios VIP
 *
 * Estrategia (más estable en iOS Safari):
 *   1. localStorage: si ya existe un token guardado, usarlo directamente.
 *   2. Hardware estable: screen, CPU, memoria, zona horaria, idioma.
 *      (Sin Canvas ni Audio — iOS los randomiza por privacidad.)
 *
 * El token se guarda en localStorage al primer login exitoso.
 * Si el usuario borra todo → hardware fallback. Si el hardware coincide → acceso silencioso.
 */

const DeviceFingerprint = {
  _cache: null,

  async generate() {
    if (this._cache) return this._cache;

    // 1. Si ya hay token guardado localmente, reutilizarlo (siempre coincide)
    const saved = this.load();
    if (saved) {
      this._cache = saved;
      return this._cache;
    }

    // 2. Generar desde señales de hardware estables
    const parts = [];

    // Pantalla
    parts.push('sw:'  + screen.width);
    parts.push('sh:'  + screen.height);
    parts.push('sd:'  + screen.colorDepth);
    parts.push('dpr:' + (window.devicePixelRatio || 1));

    // CPU / memoria
    parts.push('hw:'  + (navigator.hardwareConcurrency || 0));
    parts.push('dm:'  + (navigator.deviceMemory       || 0));
    parts.push('tp:'  + (navigator.maxTouchPoints      || 0));

    // Sistema
    parts.push('tz:'  + (Intl.DateTimeFormat().resolvedOptions().timeZone || 'unk'));
    parts.push('lg:'  + (navigator.language   || 'unk'));
    parts.push('pl:'  + (navigator.platform   || 'unk'));
    parts.push('ag:'  + (navigator.userAgent?.slice(0, 60) || 'unk'));

    this._cache = this._hash(parts.join('||'));
    return this._cache;
  },

  // FNV-1a 32-bit
  _hash(str) {
    let h = 0x811c9dc5;
    for (let i = 0; i < str.length; i++) {
      h ^= str.charCodeAt(i);
      h  = Math.imul(h, 0x01000193) >>> 0;
    }
    return h.toString(16).padStart(8, '0');
  },

  save(fp) {
    try { localStorage.setItem('_kmfp', fp);   } catch (e) {}
    try { sessionStorage.setItem('_kmfp', fp); } catch (e) {}
  },

  load() {
    try {
      return localStorage.getItem('_kmfp') || sessionStorage.getItem('_kmfp') || null;
    } catch (e) { return null; }
  },
};

window.DeviceFingerprint = DeviceFingerprint;
