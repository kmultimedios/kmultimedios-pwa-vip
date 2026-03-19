<?php
/**
 * Plugin Name: PWA VIP Auth - KMultimedios
 * Plugin URI:  https://kmultimedios.com
 * Description: PWA con autenticación WebAuthn para usuarios VIP de Paid Memberships Pro.
 *              Restringe la instalación a UN solo dispositivo por usuario.
 * Version:     1.0.0
 * Author:      KMultimedios
 * Author URI:  https://kmultimedios.com
 * Text Domain: pwa-vip-auth
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('PWA_VIP_VERSION',  '1.0.0');
define('PWA_VIP_DIR',      plugin_dir_path(__FILE__));
define('PWA_VIP_URL',      plugin_dir_url(__FILE__));
define('PWA_VIP_BASENAME', plugin_basename(__FILE__));

// ── Autoload de clases ──────────────────────────────────────────────────────
require_once PWA_VIP_DIR . 'includes/class-database.php';
require_once PWA_VIP_DIR . 'includes/class-webauthn.php';
require_once PWA_VIP_DIR . 'includes/class-pmp-integration.php';
require_once PWA_VIP_DIR . 'includes/class-streams.php';
require_once PWA_VIP_DIR . 'includes/class-api.php';
require_once PWA_VIP_DIR . 'admin/admin-page.php';

// ── Activación / Desactivación ──────────────────────────────────────────────
register_activation_hook(__FILE__, ['PWA_Database', 'install']);
register_deactivation_hook(__FILE__, '__return_true');

// ── Bootstrap ───────────────────────────────────────────────────────────────
add_action('plugins_loaded', 'pwa_vip_auth_init');

function pwa_vip_auth_init(): void {
    // REST API
    add_action('rest_api_init', ['PWA_API', 'register_routes']);

    // Admin
    if (is_admin()) {
        PWA_Admin::init();
    }

    // Shortcode [pwa_download_link]
    add_shortcode('pwa_download_link', 'pwa_vip_download_shortcode');

    // Cabeceras CORS para la PWA (solo en rutas /wp-json/pwa/)
    add_action('rest_api_init', 'pwa_vip_cors_headers', 15);
}

// ── Shortcode ────────────────────────────────────────────────────────────────
function pwa_vip_download_shortcode(array $atts = []): string {
    $atts = shortcode_atts(['texto' => 'Instalar App VIP'], $atts);

    if (!is_user_logged_in()) {
        return '<p class="pwa-notice">Debes <a href="' . wp_login_url(get_permalink()) . '">iniciar sesión</a> para acceder.</p>';
    }

    $user_id = get_current_user_id();

    if (!PWA_PMP::is_vip($user_id)) {
        return '<p class="pwa-notice pwa-notice--error">Esta función es exclusiva para miembros VIP.</p>';
    }

    $pwa_url   = home_url('/pwa/');
    $has_device = PWA_Database::user_has_device($user_id);
    $label     = esc_html($atts['texto']);

    if ($has_device) {
        return '<div class="pwa-install-wrap">
            <a href="' . esc_url($pwa_url) . '" class="pwa-btn pwa-btn--open">Abrir App VIP</a>
            <p class="pwa-notice pwa-notice--info">Dispositivo registrado. Si cambiaste de equipo, contacta soporte.</p>
        </div>';
    }

    return '<div class="pwa-install-wrap">
        <a href="' . esc_url($pwa_url) . '" class="pwa-btn pwa-btn--install">' . $label . '</a>
    </div>';
}

// ── CORS ────────────────────────────────────────────────────────────────────
function pwa_vip_cors_headers(): void {
    $origin = get_http_origin();
    $allowed = [home_url(), 'https://kmultimedios.com', 'https://www.kmultimedios.com'];

    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
    }
}
