# Changelog — KMultimedios PWA VIP

## [2026-03-28] — Proxy seguro + AES-128 + Tokens

### Nuevo servidor proxy (144.126.144.176 / proxy.kmultimedios.com)
- Contrato VPS Contabo: 6 vCPU, 12 GB RAM, 100 GB NVMe, tráfico ilimitado — $10.25/mes
- Reemplaza el servidor viejo de Contabo (videoplus)
- Ubuntu 24.04 LTS + Nginx 1.24 + PHP 8.3 + Node.js 20 + FFmpeg

### Panel de administración (proxy.kmultimedios.com/panel)
- Panel web para agregar/editar/eliminar cámaras
- Autenticación con usuario y contraseña
- 5 cámaras migradas desde el servidor viejo
- Modo proxy externo (MediaCP → proxy → cliente)

### Tokens temporales en URLs de stream
- Cada URL se firma con HMAC-SHA256 usando `SECRET_KEY`
- Token incluye: `camera_id | expires | user_id`
- Expiración: 5 minutos
- Si alguien copia la URL, en minutos deja de funcionar
- WordPress genera el token al servir las zonas (`get_zones_for_api`)

### Encriptación AES-128 del stream HLS
- Clave AES-128 generada por sesión (16 bytes aleatorios)
- IV aleatorio por sesión
- Clave almacenada en servidor, expira en 10 minutos
- Segmentos `.ts` cifrados antes de enviarlos al cliente
- Clave entregada solo a sesiones con token válido
- Compatible con iOS Safari, Android Chrome, Desktop

### Archivos modificados — Plugin WordPress
- `plugin/includes/class-streams.php`
  - `PROXY_BASE` cambiado a `https://proxy.kmultimedios.com`
  - Agregado `SECRET_KEY`, `TOKEN_EXPIRY`, `CAMERA_IDS`
  - `build_proxy_url()` ahora genera URLs con token firmado
  - `get_zones_for_api()` acepta `$user_id` para firmar tokens
- `plugin/includes/class-api.php`
  - Pasa `$user_id` a `get_zones_for_api()`

### Archivos modificados — Service Worker
- `vader/sw.js` v1.0.8
  - Ignorar peticiones a `proxy.kmultimedios.com` (no cachear HLS)
  - Versión bumped para forzar actualización en clientes

### Archivos en servidor proxy (NO en este repo)
- `/var/www/html/panel/config.php` — configuración, SECRET_KEY, funciones
- `/var/www/html/panel/serve_m3u8.php` — proxy M3U8 con tokens + AES-128
- `/var/www/html/panel/segment.php` — proxy segmentos cifrados
- `/var/www/html/panel/aes_key.php` — servidor de claves AES
- `/var/www/html/panel/dashboard.php` — panel admin
- `/var/www/html/panel/data/cameras.json` — configuración de cámaras
- `/etc/nginx/sites-available/videoplus` — configuración Nginx

---

## [2026-03-27] — WebAuthn + Fingerprint Login

### Fixes críticos
- Fix: `get_message()` → `get_error_message()` en WP_Error (fatal PHP)
- Fix: sesión token mismatch después de fingerprint login
  - Después de login silencioso, se obtiene nonce fresco via `pwa_bootstrap`
  - Resuelve "Ha fallado la comprobación de la cookie" en cámaras

### Login silencioso por fingerprint
- `fingerprint.js` reescrito: localStorage como fuente primaria (estable)
- Sin canvas/audio fingerprinting (iOS Safari los randomiza)
- Señales estables: screen, CPU, memoria, timezone, idioma, plataforma, UA, touch
- `tryFingerprintLogin()` usa admin-ajax (no REST API) para sesión completa
- Se intenta fingerprint login ANTES de mostrar pantalla de registro

### Flujo de autenticación mejorado
- Cuando `check-vip` devuelve `mobile_device: null`, intenta fingerprint igualmente
- Si registro falla con "ya tiene dispositivo" → redirige a verificación en lugar de error
- `doVerify()` llamado automáticamente en slot_full

### WebAuthn
- Bump SW a v1.0.6 → v1.0.7 para invalidar cache de shell assets

---

## Pendientes

### Alta prioridad
- [ ] Migrar `videoplus.kmultimedios.com` DNS al nuevo servidor (proxy.kmultimedios.com)
- [ ] Cancelar servidor viejo de Contabo
- [ ] Investigar por qué `check-vip` devuelve `mobile_device: null` en algunos usuarios

### Próximas sesiones
- [ ] Foro Discourse en el mismo VPS (2 GB RAM mínimo, tiene 12 GB disponibles)
  - SSO con WordPress + PMPro
  - Solo usuarios VIP activos acceden
- [ ] Mejoras al panel de streaming (estado en tiempo real, estadísticas)

---

## Arquitectura actual

```
Usuario (iPhone/Android/Desktop)
    │
    ▼
kmultimedios.com (WordPress + PMPro)
    │  WebAuthn / Fingerprint login
    │  Genera token firmado HMAC-SHA256
    │
    ▼
proxy.kmultimedios.com (Nginx + PHP 8.3)
    │  Valida token
    │  Obtiene M3U8 de MediaCP
    │  Genera clave AES-128 por sesión
    │  Cifra segmentos .ts
    │
    ▼
vdopanel.kmultimedios.com:19360 (MediaCP)
    │  Streams de cámaras originales
    │
    ▼
Usuario recibe video AES-128 cifrado
```

## Seguridad del sistema

| Vector de ataque | Antes | Ahora |
|-----------------|-------|-------|
| Copiar URL del stream | 20/100 | 70/100 (expira en 5 min) |
| Capturar tráfico de red | 20/100 | 90/100 (AES-128) |
| Usar cuenta robada | 30/100 | 85/100 (WebAuthn requerido) |
| Ataque al servidor | 80/100 | 80/100 (sin cambios) |
| **Promedio general** | **37/100** | **81/100** |
