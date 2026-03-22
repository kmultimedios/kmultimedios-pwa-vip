<?php
/**
 * REST API Endpoints para la PWA – v2.0
 *
 * GET  /check-vip           → estado VIP + ambas ranuras de dispositivo
 * GET  /challenge            → genera challenge WebAuthn + info de ranura
 * POST /register-device      → registra dispositivo en la ranura correspondiente
 * POST /verify-device        → verifica dispositivo (WebAuthn assertion)
 * GET  /my-devices           → panel usuario: sus dispositivos + cuotas
 * POST /delete-my-device     → usuario borra su propio dispositivo
 * POST /revoke-device        → admin revoca dispositivo(s)
 * GET  /content              → listado contenido PMP accesible
 * GET  /content/{id}         → contenido específico
 */

defined('ABSPATH') || exit;

class PWA_API {

    const NAMESPACE = 'pwa/v1';

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/check-vip', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'check_vip'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/challenge', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_challenge'],
            'permission_callback' => [self::class, 'require_vip'],
        ]);
        register_rest_route(self::NAMESPACE, '/register-device', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'register_device'],
            'permission_callback' => [self::class, 'require_vip'],
        ]);
        register_rest_route(self::NAMESPACE, '/verify-device', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'verify_device'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/my-devices', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_my_devices'],
            'permission_callback' => [self::class, 'require_vip'],
        ]);
        register_rest_route(self::NAMESPACE, '/delete-my-device', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'delete_my_device'],
            'permission_callback' => [self::class, 'require_vip'],
        ]);
        register_rest_route(self::NAMESPACE, '/revoke-device', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'revoke_device'],
            'permission_callback' => [self::class, 'require_admin'],
        ]);
        register_rest_route(self::NAMESPACE, '/content', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_content_list'],
            'permission_callback' => [self::class, 'require_vip_device'],
        ]);
        register_rest_route(self::NAMESPACE, '/content/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_content_single'],
            'permission_callback' => [self::class, 'require_vip_device'],
        ]);
        register_rest_route(self::NAMESPACE, '/streams', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_streams'],
            'permission_callback' => [self::class, 'require_vip_device'],
        ]);
    }

    // ── Permission callbacks ─────────────────────────────────────────────────

    public static function require_vip(): bool|WP_Error {
        if (!is_user_logged_in()) {
            return new WP_Error('not_authenticated', 'Debes iniciar sesión.', ['status' => 401]);
        }
        if (!PWA_PMP::is_vip(get_current_user_id())) {
            return new WP_Error('not_vip', 'Acceso exclusivo para miembros VIP.', ['status' => 403]);
        }
        return true;
    }

    public static function require_vip_device(): bool|WP_Error {
        $vip = self::require_vip();
        if (is_wp_error($vip)) return $vip;

        $user_id     = get_current_user_id();
        $device_type = PWA_Database::detect_device_type();

        if (!PWA_Database::user_has_device_for_type($user_id, $device_type)) {
            return new WP_Error('no_device_for_slot', 'Este dispositivo no está registrado.', ['status' => 403]);
        }
        return true;
    }

    public static function require_admin(): bool|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error('not_admin', 'Acceso denegado.', ['status' => 403]);
        }
        return true;
    }

    // ── Endpoints ────────────────────────────────────────────────────────────

    public static function check_vip(WP_REST_Request $request): WP_REST_Response {
        // El REST API ignora cookies sin nonce (protección CSRF).
        // check-vip es el endpoint bootstrap: buscamos la cookie logged_in
        // para poder devolver el nonce que usarán todas las llamadas posteriores.
        if (!is_user_logged_in()) {
            foreach ($_COOKIE as $name => $value) {
                if (str_starts_with($name, 'wordpress_logged_in_')) {
                    $uid = wp_validate_auth_cookie($value, 'logged_in');
                    if ($uid) {
                        wp_set_current_user($uid);
                        break;
                    }
                }
            }
        }

        if (!is_user_logged_in()) {
            return self::response([
                'is_logged_in'        => false,
                'is_vip'              => false,
                'current_device_type' => PWA_Database::detect_device_type(),
                'mobile_device'       => null,
                'desktop_device'      => null,
            ]);
        }

        $user_id     = get_current_user_id();
        $user        = wp_get_current_user();
        $is_vip      = PWA_PMP::is_vip($user_id);
        $device_type = PWA_Database::detect_device_type();
        $devices     = $is_vip ? PWA_Database::get_all_user_devices($user_id) : ['mobile' => null, 'desktop' => null];
        $year        = (int) date('Y');

        return self::response([
            'is_logged_in'                  => true,
            'is_vip'                        => $is_vip,
            'user_id'                       => $user_id,
            'display_name'                  => $user->display_name,
            'email'                         => $user->user_email,
            'level_name'                    => $is_vip ? PWA_PMP::get_level_name($user_id) : '',
            'expiry'                        => $is_vip ? PWA_PMP::get_expiry($user_id) : null,
            'current_device_type'           => $device_type,
            'mobile_device'                 => $devices['mobile'] ? self::format_device($devices['mobile']) : null,
            'desktop_device'                => $devices['desktop'] ? self::format_device($devices['desktop']) : null,
            'mobile_replacements_this_year' => $is_vip ? PWA_Database::get_replacement_count($user_id, 'mobile', $year)  : 0,
            'desktop_replacements_this_year'=> $is_vip ? PWA_Database::get_replacement_count($user_id, 'desktop', $year) : 0,
            'mobile_blocked'                => $is_vip && PWA_Database::user_is_blocked_for_slot($user_id, 'mobile'),
            'desktop_blocked'               => $is_vip && PWA_Database::user_is_blocked_for_slot($user_id, 'desktop'),
            'max_replacements'              => PWA_Database::MAX_REPLACEMENTS_PER_YEAR,
            'nonce'                         => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function get_challenge(WP_REST_Request $request): WP_REST_Response {
        $user_id     = get_current_user_id();
        $device_type = PWA_Database::detect_device_type();
        $has_slot    = PWA_Database::user_has_device_for_type($user_id, $device_type);
        $is_blocked  = !$has_slot && PWA_Database::user_is_blocked_for_slot($user_id, $device_type);
        $challenge   = PWA_WebAuthn::generate_challenge($user_id);

        // Si el usuario ya tiene dispositivo, devolver credential_id para allowCredentials
        $credential_id = null;
        if ($has_slot) {
            $device = PWA_Database::get_device_by_user_and_type($user_id, $device_type);
            $credential_id = $device ? $device->credential_id : null;
        }

        return self::response([
            'challenge'     => $challenge,
            'rp_id'         => PWA_WebAuthn::RP_ID,
            'user_id'       => $user_id,
            'device_type'   => $device_type,
            'slot_available'=> !$has_slot,
            'is_blocked'    => $is_blocked,
            'credential_id' => $credential_id,
        ]);
    }

    public static function register_device(WP_REST_Request $request): WP_REST_Response {
        $user_id     = get_current_user_id();
        $device_type = PWA_Database::detect_device_type();

        // Ranura ya ocupada
        if (PWA_Database::user_has_device_for_type($user_id, $device_type)) {
            $slot_label = $device_type === 'mobile' ? 'móvil' : 'escritorio';
            return self::error('slot_full', "Ya tienes un dispositivo {$slot_label} registrado. Elimínalo desde tu panel de dispositivos para registrar uno nuevo.", 409);
        }

        // Cuota anual agotada
        if (PWA_Database::user_is_blocked_for_slot($user_id, $device_type)) {
            $next_year = (int) date('Y') + 1;
            return self::error('yearly_cap', "Has alcanzado el límite de reemplazos anuales para dispositivos de tipo " . ($device_type === 'mobile' ? 'móvil' : 'escritorio') . ". Disponible desde el 1 de enero de {$next_year}.", 403);
        }

        $body     = $request->get_json_params();
        $response = $body['response'] ?? [];

        if (empty($response)) {
            return self::error('missing_data', 'Datos de registro incompletos.', 400);
        }

        $result = PWA_WebAuthn::verify_registration($user_id, $response);
        if (is_wp_error($result)) {
            return self::error($result->get_error_code(), $result->get_message(), 400);
        }

        $device_name = sanitize_text_field($body['device_name'] ?? '') ?: self::detect_device_name($request, $device_type);

        $saved = PWA_Database::save_device(
            $user_id,
            $device_type,
            $result['credential_id'],
            $result['public_key'],
            $device_name
        );

        if (!$saved) {
            return self::error('db_error', 'Error guardando el dispositivo.', 500);
        }

        self::log_event('device_registered', $user_id, ['device_type' => $device_type, 'device_name' => $device_name]);

        return self::response([
            'success'     => true,
            'message'     => 'Dispositivo registrado correctamente.',
            'device_name' => $device_name,
            'device_type' => $device_type,
        ]);
    }

    public static function verify_device(WP_REST_Request $request): WP_REST_Response {
        $body        = $request->get_json_params();
        $user_id     = (int) ($body['user_id'] ?? 0);
        $device_type = PWA_Database::detect_device_type();

        if (!$user_id) {
            return self::error('missing_user', 'user_id requerido.', 400);
        }
        if (!PWA_PMP::is_vip($user_id)) {
            return self::error('not_vip', 'Acceso exclusivo para miembros VIP.', 403);
        }

        $device = PWA_Database::get_device_by_user_and_type($user_id, $device_type);
        if (!$device) {
            // El dispositivo de esta ranura no existe → puede registrarse
            return self::error('no_device_for_slot', 'Este tipo de dispositivo no está registrado. Por favor, regístralo.', 404);
        }

        $response = $body['response'] ?? [];
        $result   = PWA_WebAuthn::verify_assertion($user_id, $response, $device);

        if (is_wp_error($result)) {
            self::log_event('verify_failed', $user_id, ['error' => $result->get_error_code(), 'device_type' => $device_type]);
            return self::error($result->get_error_code(), $result->get_message(), 400);
        }

        // Establecer sesión WordPress (siempre, para renovar cookie y nonce)
        wp_set_current_user($user_id);

        self::log_event('device_verified', $user_id, ['device_type' => $device_type]);

        $user = get_userdata($user_id);
        return self::response([
            'success'      => true,
            'message'      => 'Dispositivo verificado.',
            'display_name' => $user->display_name,
            'level_name'   => PWA_PMP::get_level_name($user_id),
            'device_type'  => $device_type,
            'nonce'        => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function get_my_devices(WP_REST_Request $request): WP_REST_Response {
        $user_id     = get_current_user_id();
        $year        = (int) date('Y');
        $device_type = PWA_Database::detect_device_type();
        $devices     = PWA_Database::get_all_user_devices($user_id);

        $mobile_count  = PWA_Database::get_replacement_count($user_id, 'mobile',  $year);
        $desktop_count = PWA_Database::get_replacement_count($user_id, 'desktop', $year);

        return self::response([
            'mobile_device'                  => $devices['mobile']  ? self::format_device($devices['mobile'])  : null,
            'desktop_device'                 => $devices['desktop'] ? self::format_device($devices['desktop']) : null,
            'current_device_type'            => $device_type,
            'mobile_replacements_this_year'  => $mobile_count,
            'desktop_replacements_this_year' => $desktop_count,
            'mobile_blocked'                 => $mobile_count  >= PWA_Database::MAX_REPLACEMENTS_PER_YEAR,
            'desktop_blocked'                => $desktop_count >= PWA_Database::MAX_REPLACEMENTS_PER_YEAR,
            'max_replacements'               => PWA_Database::MAX_REPLACEMENTS_PER_YEAR,
            'year'                           => $year,
            'reset_date'                     => ($year + 1) . '-01-01',
        ]);
    }

    public static function delete_my_device(WP_REST_Request $request): WP_REST_Response {
        $user_id   = get_current_user_id();
        $body      = $request->get_json_params();
        $device_id = (int) ($body['device_id'] ?? 0);

        if (!$device_id) {
            return self::error('missing_device_id', 'device_id requerido.', 400);
        }

        // Verificar que el dispositivo pertenece al usuario actual
        $device = PWA_Database::get_device_by_id($device_id);
        if (!$device || (int) $device->user_id !== $user_id) {
            return self::error('not_found', 'Dispositivo no encontrado.', 404);
        }
        if (!$device->is_active) {
            return self::error('already_inactive', 'El dispositivo ya estaba eliminado.', 409);
        }

        $device_type = $device->device_type;
        $year        = (int) date('Y');

        // Verificar cuota anual ANTES de borrar
        if (PWA_Database::user_is_blocked_for_slot($user_id, $device_type)) {
            $next_year = $year + 1;
            $slot_label = $device_type === 'mobile' ? 'móvil' : 'escritorio';
            return self::error(
                'yearly_cap',
                "Has usado los {PWA_Database::MAX_REPLACEMENTS_PER_YEAR} reemplazos permitidos este año para tu dispositivo {$slot_label}. Podrás cambiar de nuevo el 1 de enero de {$next_year}.",
                403
            );
        }

        // Revocar y contabilizar
        PWA_Database::revoke_device_by_id($device_id, true);
        PWA_Database::increment_replacement_count($user_id, $device_type, $year);

        $new_count = PWA_Database::get_replacement_count($user_id, $device_type, $year);
        $blocked   = $new_count >= PWA_Database::MAX_REPLACEMENTS_PER_YEAR;

        self::log_event('device_deleted_by_user', $user_id, [
            'device_id'   => $device_id,
            'device_type' => $device_type,
            'replacements_used' => $new_count,
        ]);

        $slot_label = $device_type === 'mobile' ? 'móvil' : 'escritorio';
        return self::response([
            'success'           => true,
            'message'           => "Dispositivo {$slot_label} eliminado. La ranura está libre para registrar uno nuevo.",
            'device_type'       => $device_type,
            'replacements_used' => $new_count,
            'replacements_left' => max(0, PWA_Database::MAX_REPLACEMENTS_PER_YEAR - $new_count),
            'blocked'           => $blocked,
        ]);
    }

    public static function revoke_device(WP_REST_Request $request): WP_REST_Response {
        $body    = $request->get_json_params();
        $user_id = (int) ($body['user_id'] ?? 0);

        if (!$user_id) {
            return self::error('missing_user', 'user_id requerido.', 400);
        }

        $revoked = PWA_Database::admin_revoke_all_devices($user_id);
        if (!$revoked) {
            return self::error('revoke_failed', 'No se pudo revocar el dispositivo.', 500);
        }

        self::log_event('device_revoked_admin', get_current_user_id(), ['target_user' => $user_id]);
        return self::response(['success' => true, 'message' => 'Dispositivos revocados.']);
    }

    public static function get_content_list(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $posts   = PWA_PMP::get_accessible_content($user_id);

        $items = array_map(function ($post) {
            return [
                'id'        => $post->ID,
                'title'     => get_the_title($post),
                'excerpt'   => get_the_excerpt($post),
                'date'      => get_the_date('c', $post),
                'thumbnail' => get_the_post_thumbnail_url($post, 'medium') ?: null,
                'type'      => $post->post_type,
                'url'       => get_permalink($post),
            ];
        }, $posts);

        return self::response(['items' => array_values($items), 'total' => count($items)]);
    }

    public static function get_content_single(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $post_id = (int) $request->get_param('id');
        $post    = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return self::error('not_found', 'Contenido no encontrado.', 404);
        }
        if (function_exists('pmpro_has_membership_access') && !pmpro_has_membership_access($post_id, $user_id)) {
            return self::error('no_access', 'No tienes acceso a este contenido.', 403);
        }

        return self::response([
            'id'        => $post->ID,
            'title'     => get_the_title($post),
            'content'   => apply_filters('the_content', $post->post_content),
            'date'      => get_the_date('c', $post),
            'thumbnail' => get_the_post_thumbnail_url($post, 'large') ?: null,
            'url'       => get_permalink($post),
            'author'    => get_the_author_meta('display_name', $post->post_author),
        ]);
    }

    public static function get_streams(WP_REST_Request $request): WP_REST_Response {
        $user_id   = get_current_user_id();
        $zones     = PWA_Streams::get_zones_for_api();
        $watermark = PWA_Streams::get_watermark($user_id);

        return self::response([
            'zones'      => array_values($zones),
            'audio_url'  => PWA_Streams::AUDIO_URL,
            'watermark'  => $watermark,
            'total_cams' => PWA_Streams::count_cameras(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function format_device(object $d): array {
        return [
            'id'              => (int) $d->id,
            'device_type'     => $d->device_type,
            'device_name'     => $d->device_name,
            'registered_date' => $d->registered_date,
            'last_access'     => $d->last_access,
        ];
    }

    private static function response(array $data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response($data, $status);
    }

    private static function error(string $code, string $message, int $status): WP_REST_Response {
        return new WP_REST_Response(['error' => $code, 'message' => $message], $status);
    }

    private static function detect_device_name(WP_REST_Request $request, string $device_type): string {
        $ua = $request->get_header('user_agent') ?? '';
        if (str_contains($ua, 'iPhone'))   return 'iPhone';
        if (str_contains($ua, 'iPad'))     return 'iPad';
        if (str_contains($ua, 'Android'))  return 'Android';
        if (str_contains($ua, 'Windows'))  return 'PC Windows';
        if (str_contains($ua, 'Macintosh')) return 'Mac';
        if (str_contains($ua, 'Linux'))    return 'Linux';
        return $device_type === 'mobile' ? 'Móvil' : 'Escritorio';
    }

    private static function log_event(string $event, int $user_id, array $data): void {
        $logs   = get_option('pwa_vip_access_log', []);
        $logs[] = [
            'event'   => $event,
            'user_id' => $user_id,
            'data'    => $data,
            'time'    => current_time('mysql'),
            'ip'      => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }
        update_option('pwa_vip_access_log', $logs, false);
    }
}
