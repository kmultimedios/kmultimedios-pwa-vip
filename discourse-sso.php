<?php
require_once dirname(__FILE__) . '/wp-load.php';

define('KM_DISCOURSE_SSO_SECRET', 'e9bbafb36b18cd926aad40be8220ed7069e505402d8ad3a1bfae3328087ad5d7');

$sso     = $_GET['sso']    ?? '';
$sig     = $_GET['sig']    ?? '';
$km_key  = $_GET['km_key'] ?? '';

// Paso 2: volvemos del login de WP — recuperar params del transient
// NO borramos el transient aquí: Safari hace prefetch/preload que lo consumiría
// antes de la navegación real. El nonce de Discourse ya protege contra replay.
if ($km_key && !$sso) {
    $saved = get_transient('discourse_sso_' . $km_key);
    if ($saved) {
        $sso = $saved['sso'];
        $sig = $saved['sig'];
    }
}

if (empty($sso) || empty($sig)) die('Parámetros SSO faltantes');

if (!hash_equals(hash_hmac('sha256', $sso, KM_DISCOURSE_SSO_SECRET), $sig)) {
    die('Firma inválida');
}

// Paso 1: si no está logueado guardar y redirigir a login
if (!is_user_logged_in()) {
    $key = wp_generate_password(12, false);
    set_transient('discourse_sso_' . $key, ['sso' => $sso, 'sig' => $sig], 10 * MINUTE_IN_SECONDS);
    $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/discourse-sso.php?km_key=' . $key;
    wp_redirect(wp_login_url($return_url));
    exit;
}

parse_str(base64_decode($sso), $params);
$nonce          = $params['nonce']          ?? '';
$return_sso_url = $params['return_sso_url'] ?? '';
if (!$nonce || !$return_sso_url) die('Payload inválido');

$user   = wp_get_current_user();
$is_vip = function_exists('pmpro_hasMembershipLevel')
    && pmpro_hasMembershipLevel([4, 5, 8, 9, 10, 11, 12], $user->ID);

$payload = [
    'nonce'           => $nonce,
    'email'           => $user->user_email,
    'external_id'     => $user->ID,
    'username'        => $user->user_login,
    'name'            => $user->display_name,
    'destination_url' => '/',
];

// Sincronizar grupo vip-vader según membresía PMP
if ($is_vip) {
    $payload['add_groups']    = 'vip-vader';
    $payload['remove_groups'] = '';
} else {
    $payload['add_groups']    = '';
    $payload['remove_groups'] = 'vip-vader';
}

$response = base64_encode(http_build_query($payload));
$sig_out      = hash_hmac('sha256', $response, KM_DISCOURSE_SSO_SECRET);
$redirect_url = $return_sso_url . '?sso=' . rawurlencode($response) . '&sig=' . $sig_out;

// JS redirect evita el doble-request especulativo de Chrome/Edge con 302
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache');
echo '<!DOCTYPE html><html><head><meta charset="utf-8">
<script>window.location.replace(' . json_encode($redirect_url) . ');</script>
</head><body>Redirigiendo...</body></html>';
exit;
