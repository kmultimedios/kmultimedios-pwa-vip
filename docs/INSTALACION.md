# Guía de Instalación – PWA VIP Auth para KMultimedios v2.0

## Requisitos previos

- WordPress con **PHP 8.0+**
- Plugin **Paid Memberships Pro** instalado y activado
- **HTTPS** habilitado en el dominio (obligatorio para WebAuthn)
- Dominio: `kmultimedios.com`

---

## PASO 1: Instalar el Plugin WordPress

1. Copia la carpeta `plugin/` a:
   ```
   wp-content/plugins/pwa-vip-auth/
   ```
   La estructura debe quedar así:
   ```
   wp-content/plugins/pwa-vip-auth/
   ├── pwa-vip-auth.php          ← archivo principal
   ├── includes/
   │   ├── class-database.php
   │   ├── class-webauthn.php
   │   ├── class-pmp-integration.php
   │   ├── class-streams.php     ← configuración de cámaras/zonas
   │   └── class-api.php
   └── admin/
       └── admin-page.php
   ```

2. Activa el plugin en **WordPress Admin → Plugins → PWA VIP Auth**

3. Al activar se crean automáticamente **2 tablas** en la base de datos:
   - `wp_pwa_user_devices` — dispositivos registrados (con ranura mobile/desktop)
   - `wp_pwa_device_replacements` — cuota anual de reemplazos por usuario

---

## PASO 2: Subir la PWA

1. Copia la carpeta `pwa/` a la raíz de tu sitio:
   ```
   public_html/pwa/
   ```
   La estructura debe quedar así:
   ```
   public_html/pwa/
   ├── index.html
   ├── manifest.json
   ├── sw.js
   ├── css/
   │   └── app.css
   ├── js/
   │   ├── webauthn.js
   │   ├── auth.js
   │   ├── cameras.js     ← Command Center + Radio Player
   │   └── app.js
   └── icons/
       ├── icon-72.png
       ├── icon-96.png
       ├── icon-128.png
       ├── icon-144.png
       ├── icon-152.png
       ├── icon-192.png
       ├── icon-384.png
       └── icon-512.png
   ```

   La PWA será accesible en: `https://kmultimedios.com/pwa/`

2. **Iconos**: Genera los iconos PNG en los tamaños indicados y colócalos en `pwa/icons/`. Puedes usar:
   - https://favicon.io/favicon-generator/
   - https://maskable.app/editor (para iconos maskable)

---

## PASO 3: Configurar el .htaccess

Agrega esto al `.htaccess` de la raíz del sitio para que el Service Worker funcione correctamente:

```apache
# Headers para PWA
<FilesMatch "sw\.js$">
    Header set Cache-Control "no-cache"
    Header set Service-Worker-Allowed "/"
</FilesMatch>

<FilesMatch "manifest\.json$">
    Header set Content-Type "application/manifest+json"
</FilesMatch>

# HTTPS redirect (si no lo tienes ya)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## PASO 4: Verificar Niveles VIP

Los IDs de niveles VIP configurados son: **4, 5, 8, 9, 10, 11**

Para cambiarlos edita `includes/class-pmp-integration.php`:
```php
const VIP_LEVELS = [4, 5, 8, 9, 10, 11];
```

---

## PASO 5: Agregar el Shortcode (opcional)

En la página donde quieras mostrar un botón de acceso a la PWA:
```
[pwa_download_link]
```
o con texto personalizado:
```
[pwa_download_link texto="Acceder a mi App VIP"]
```

Este shortcode **solo muestra el botón a usuarios VIP con membresía activa**.

---

## Panel de Administración

Accede desde **WordPress Admin → PWA VIP** para:
- Ver todos los dispositivos registrados (móvil + escritorio por usuario)
- Revocar dispositivos manualmente (emergencias / soporte)
- Ver el registro de accesos con IP y timestamps

---

## Endpoints API Disponibles

| Método | Endpoint | Descripción | Acceso |
|--------|----------|-------------|--------|
| GET | `/wp-json/pwa/v1/check-vip` | Estado VIP + ambas ranuras de dispositivo | Público |
| GET | `/wp-json/pwa/v1/challenge` | Genera challenge WebAuthn para la ranura actual | VIP |
| POST | `/wp-json/pwa/v1/register-device` | Registra dispositivo en la ranura (mobile/desktop) | VIP |
| POST | `/wp-json/pwa/v1/verify-device` | Verifica dispositivo biométrico y crea sesión | Público |
| GET | `/wp-json/pwa/v1/my-devices` | Panel usuario: dispositivos + cuotas anuales | VIP |
| POST | `/wp-json/pwa/v1/delete-my-device` | Usuario borra su dispositivo (gasta 1 reemplazo) | VIP |
| POST | `/wp-json/pwa/v1/revoke-device` | Admin revoca dispositivos de un usuario | Admin |
| GET | `/wp-json/pwa/v1/streams` | Zonas, cámaras HLS + watermark del usuario | VIP + dispositivo |
| GET | `/wp-json/pwa/v1/content` | Lista contenido accesible por membresía | VIP + dispositivo |
| GET | `/wp-json/pwa/v1/content/{id}` | Contenido específico | VIP + dispositivo |

---

## Flujo de Usuario

```
Usuario → Visita /pwa/ → Check VIP
    ├── No logueado          → Pantalla "Inicia sesión" → redirige a wp-login.php
    ├── No VIP               → Pantalla "Acceso VIP requerido" → enlace a planes
    ├── VIP + sin dispositivo en esta ranura
    │       ├── Cuota anual agotada  → Pantalla "Límite anual alcanzado" (reset 1 ene)
    │       └── Disponible           → Pantalla "Registrar dispositivo"
    │               └── Biometría (Face ID / Huella / Windows Hello)
    │                       └── POST /register-device → ✅ Acceso al Command Center
    └── VIP + dispositivo registrado en esta ranura
            └── Biometría automática → POST /verify-device → ✅ Acceso al Command Center
```

**Sistema de ranuras:**
- Cada usuario VIP tiene **2 ranuras**: `mobile` (iPhone/Android) y `desktop` (Windows Hello/Mac Touch ID)
- La ranura se detecta automáticamente por el User-Agent del navegador
- Cada ranura permite hasta **2 reemplazos por año** (se resetea el 1 de enero)
- El usuario gestiona sus propios dispositivos desde la PWA (sin contactar soporte)

---

## Cambio de Dispositivo (autoservicio del usuario)

El usuario puede cambiar su dispositivo **desde la propia PWA**:
1. Entrar al **Command Center** con biometría
2. Ir a la pestaña **"Mis Dispositivos"** (ícono 📲 en la barra inferior)
3. Hacer clic en **"Eliminar y registrar otro"** en la ranura correspondiente
4. Confirmar el modal — esto gasta 1 reemplazo de la cuota anual
5. La ranura queda libre inmediatamente para registrar el nuevo dispositivo
6. La próxima vez que entren desde ese dispositivo nuevo, se registra con biometría

**Nota:** Si el usuario agota los 2 reemplazos anuales, el administrador puede revocar manualmente desde el panel de admin (sin costo de cuota).

---

## Configuración de Cámaras y Streams

Las zonas y cámaras están centralizadas en `includes/class-streams.php`. Para agregar, quitar o modificar cámaras edita únicamente ese archivo — el método `get_zones()` contiene toda la configuración.

```php
// Ejemplo de estructura de una cámara:
[
    'hls_key'   => 'proxy_XXXXX',          // para streams via videoplus.kmultimedios.com
    // — o bien —
    'hls_url'   => 'https://..../index.m3u8', // para streams HLS directos
    'name'      => 'Nombre de la cámara',
    'code'      => 'ZON-XX-01',
    'location'  => 'Ciudad ESTADO',
    'type'      => 'TRÁFICO',
    'priority'  => 'high',                  // high = inicia automáticamente
    'audio_url' => PWA_Streams::AUDIO_URL,  // opcional
]
```

Para actualizar las URLs de los streams proxy:
- `PROXY_BASE` en `class-streams.php` → `https://videoplus.kmultimedios.com`
- `AUDIO_URL` → `https://streamers.kmultiradio.com/8002/stream`

---

## Seguridad Implementada

- ✅ Challenge aleatorio de 32 bytes generado en servidor (one-time, TTL 5 min)
- ✅ Verificación de origen HTTPS y `clientDataJSON`
- ✅ Verificación de flags UP (User Present) y UV (User Verified)
- ✅ Protección contra replay attacks (sign_count incremental)
- ✅ Cuota anual de reemplazos verificada ANTES de revocar el dispositivo
- ✅ URLs HLS proxy nunca expuestas al cliente (solo URL final construida en servidor)
- ✅ Watermark por usuario en cada video (email, IP, UID, nivel, timestamp)
- ✅ Nonces de WordPress en todos los requests autenticados
- ✅ CORS restringido a dominios propios
- ✅ Logs de acceso con IP y timestamp (últimos 500 eventos)
- ✅ HTTPS obligatorio (WebAuthn no funciona en HTTP)

---

## Dependencias Externas (CDN — ya incluidas en index.html)

| Librería | Versión | Uso |
|----------|---------|-----|
| hls.js | 1.x | Reproducción de streams HLS en todos los navegadores |
| Font Awesome | 6.5 | Íconos de dirección en el Command Center |
| Google Fonts | — | Orbitron + Share Tech Mono (UI Command Center) |

No se requiere instalación adicional — se cargan desde CDN en el `index.html`.

---

## Notas de Producción

- Configura **rate limiting** en `/wp-json/pwa/` (ej. WP Cerber, Cloudflare Rules)
- Activa **caché de assets estáticos** de la PWA (WP Rocket o similar) excepto `sw.js`
- El Service Worker versiona el caché como `km-vip-v1.0.0` — incrementa la versión en `sw.js` cuando subas actualizaciones
- Monitorea el log de accesos regularmente desde **Admin → PWA VIP**
- Para producción de alta seguridad considera la librería PHP `web-auth/webauthn-framework` para verificación COSE completa
