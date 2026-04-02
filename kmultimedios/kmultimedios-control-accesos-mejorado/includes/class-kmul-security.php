<?php
/**
 * Manejo de seguridad, validaciones y verificaciones
 * 
 * @since 5.0.0
 */
class KMUL_Security {
    
    private $database;
    
    public function __construct(KMUL_Database $database) {
        $this->database = $database;
    }
    
    /**
     * Verificar si un usuario bloqueado está intentando acceder
     */
    public function check_blocked_user() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        if ($this->database->is_user_blocked($current_user->ID)) {
            $this->handle_blocked_user_access($current_user);
        }
    }
    
    /**
     * Manejar acceso de usuario bloqueado
     */
    private function handle_blocked_user_access($user) {
        // Registrar intento de acceso
        error_log('KMUL: Blocked user attempted access: ' . $user->user_login);
        
        // Cerrar sesión
        wp_logout();
        
        // Destruir todas las sesiones del usuario
        $sessions = WP_Session_Tokens::get_instance($user->ID);
        $sessions->destroy_all();
        
        // Redirigir con mensaje
        wp_redirect(wp_login_url() . '?blocked=1');
        exit;
    }
    
    /**
     * Verificar requests AJAX con nonce y permisos
     */
    public function verify_ajax_request($action = 'kmul_ajax_action') {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            return false;
        }
        
        // Verificar permisos de administrador
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Verificar method POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitizar y validar entrada de usuario
     */
    public function sanitize_user_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'int':
                return absint($input);
            case 'float':
                return floatval($input);
            case 'url':
                return esc_url_raw($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'html':
                return wp_kses_post($input);
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Validar IP address
     */
    public function validate_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Detectar intentos de acceso sospechosos
     */
    public function detect_suspicious_access($user_id, $ip, $user_agent) {
        $suspicious_indicators = 0;
        $warnings = [];
        
        // IP desde país diferente (si tienes servicio de geolocalización)
        // $country_change = $this->check_country_change($user_id, $ip);
        
        // User agent completamente diferente
        $ua_change = $this->check_dramatic_user_agent_change($user_id, $user_agent);
        if ($ua_change) {
            $suspicious_indicators++;
            $warnings[] = 'Cambio drástico en user agent';
        }
        
        // Múltiples intentos desde misma IP en poco tiempo
        $rapid_attempts = $this->check_rapid_login_attempts($ip);
        if ($rapid_attempts) {
            $suspicious_indicators++;
            $warnings[] = 'Múltiples intentos de login desde misma IP';
        }
        
        // Acceso fuera de horarios habituales
        $unusual_time = $this->check_unusual_access_time($user_id);
        if ($unusual_time) {
            $suspicious_indicators++;
            $warnings[] = 'Acceso en horario inusual';
        }
        
        return [
            'is_suspicious' => $suspicious_indicators >= 2,
            'score' => $suspicious_indicators,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Verificar cambio drástico en user agent
     */
    private function check_dramatic_user_agent_change($user_id, $current_ua) {
        global $wpdb;
        $table_logs = $this->database->get_table_names()['logs'];
        
        $recent_ua = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_agent FROM {$table_logs} 
                 WHERE user_id = %d AND login_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY login_date DESC LIMIT 1",
                $user_id
            )
        );
        
        if (!$recent_ua) {
            return false;
        }
        
        // Comparar características principales
        $current_parts = $this->extract_ua_parts($current_ua);
        $recent_parts = $this->extract_ua_parts($recent_ua);
        
        $differences = 0;
        if ($current_parts['os'] !== $recent_parts['os']) $differences++;
        if ($current_parts['browser'] !== $recent_parts['browser']) $differences++;
        if ($current_parts['device'] !== $recent_parts['device']) $differences++;
        
        return $differences >= 2; // Cambio en 2 o más características principales
    }
    
    /**
     * Extraer partes principales del user agent
     */
    private function extract_ua_parts($user_agent) {
        $detector = new KMUL_Detector($this->database);
        $device_info = $detector->detect_device_info($user_agent, '0.0.0.0');
        
        return [
            'os' => $device_info['operating_system'] ?? 'unknown',
            'browser' => $device_info['browser'] ?? 'unknown', 
            'device' => $device_info['device_type'] ?? 'unknown'
        ];
    }
    
    /**
     * Verificar intentos rápidos de login
     */
    private function check_rapid_login_attempts($ip) {
        global $wpdb;
        $table_logs = $this->database->get_table_names()['logs'];
        
        $recent_attempts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_logs} 
                 WHERE ip_address = %s AND login_date > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                $ip
            )
        );
        
        return $recent_attempts > 5; // Más de 5 intentos en 5 minutos
    }
    
    /**
     * Verificar acceso en horario inusual
     */
    private function check_unusual_access_time($user_id) {
        global $wpdb;
        $table_logs = $this->database->get_table_names()['logs'];
        
        // Obtener horarios típicos del usuario (últimos 30 días)
        $typical_hours = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT HOUR(login_date) as hour, COUNT(*) as count
                 FROM {$table_logs} 
                 WHERE user_id = %d AND login_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY HOUR(login_date)
                 ORDER BY count DESC",
                $user_id
            )
        );
        
        if (empty($typical_hours)) {
            return false; // No hay historial suficiente
        }
        
        $current_hour = (int) current_time('H');
        $user_hours = array_column($typical_hours, 'hour');
        
        // Si nunca ha accedido a esta hora, es sospechoso
        return !in_array($current_hour, $user_hours);
    }
    
    /**
     * Rate limiting para acciones sensibles
     */
    public function check_rate_limit($action, $identifier, $limit = 5, $window = 300) {
        $transient_key = "kmul_rate_limit_{$action}_{$identifier}";
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // Primera vez, inicializar
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($attempts >= $limit) {
            return false; // Rate limit excedido
        }
        
        // Incrementar contador
        set_transient($transient_key, $attempts + 1, $window);
        return true;
    }
    
    /**
     * Verificar fuerza de contraseña (si es relevante)
     */
    public function check_password_strength($password) {
        $score = 0;
        $feedback = [];
        
        // Longitud
        if (strlen($password) >= 12) {
            $score += 2;
        } elseif (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = 'Contraseña muy corta';
        }
        
        // Complejidad
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
        
        // Patrones comunes
        $common_patterns = [
            '/(.)\1{2,}/', // Caracteres repetidos
            '/123456/', // Secuencias numéricas
            '/password|admin|user/i' // Palabras comunes
        ];
        
        foreach ($common_patterns as $pattern) {
            if (preg_match($pattern, $password)) {
                $score -= 2;
                $feedback[] = 'Contiene patrones comunes';
                break;
            }
        }
        
        $strength = 'weak';
        if ($score >= 6) $strength = 'strong';
        elseif ($score >= 4) $strength = 'medium';
        
        return [
            'score' => max(0, $score),
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }
    
    /**
     * Generar token seguro para verificaciones
     */
    public function generate_secure_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verificar token temporal
     */
    public function verify_token($token, $user_id, $action) {
        $stored_token = get_user_meta($user_id, "kmul_token_{$action}", true);
        
        if (!$stored_token || !hash_equals($stored_token['token'], $token)) {
            return false;
        }
        
        // Verificar expiración (24 horas por defecto)
        if (time() > $stored_token['expires']) {
            delete_user_meta($user_id, "kmul_token_{$action}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Crear token temporal
     */
    public function create_token($user_id, $action, $expires_in = 86400) {
        $token = $this->generate_secure_token();
        
        $token_data = [
            'token' => $token,
            'expires' => time() + $expires_in,
            'created' => time()
        ];
        
        update_user_meta($user_id, "kmul_token_{$action}", $token_data);
        
        return $token;
    }
    
    /**
     * Limpiar tokens expirados
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        $expired_tokens = $wpdb->get_results(
            "SELECT user_id, meta_key FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'kmul_token_%'"
        );
        
        $cleaned = 0;
        foreach ($expired_tokens as $token_meta) {
            $token_data = get_user_meta($token_meta->user_id, $token_meta->meta_key, true);
            
            if (is_array($token_data) && isset($token_data['expires'])) {
                if (time() > $token_data['expires']) {
                    delete_user_meta($token_meta->user_id, $token_meta->meta_key);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Registrar evento de seguridad
     */
    public function log_security_event($event_type, $user_id, $details = []) {
        $log_entry = [
            'timestamp' => current_time('c'),
            'event_type' => $event_type,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'details' => $details
        ];
        
        // Guardar en archivo de log específico
        $log_file = wp_upload_dir()['basedir'] . '/kmul-security.log';
        $log_line = date('Y-m-d H:i:s') . ' - ' . json_encode($log_entry) . PHP_EOL;
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // También registrar en error_log si está habilitado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KMUL Security Event: ' . json_encode($log_entry));
        }
    }
    
    /**
     * Verificar permisos específicos del plugin
     */
    public function user_can_manage_access_control($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Permitir a administradores y roles con capacidad específica
        return user_can($user_id, 'manage_options') || user_can($user_id, 'kmul_manage_access');
    }
    
    /**
     * Agregar capacidades del plugin a roles
     */
    public function setup_plugin_capabilities() {
        // Agregar capacidades a administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('kmul_manage_access');
            $admin_role->add_cap('kmul_view_reports');
            $admin_role->add_cap('kmul_export_data');
        }
        
        // Opcional: agregar a otros roles según necesidades
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('kmul_view_reports');
        }
    }
    
    /**
     * Remover capacidades del plugin
     */
    public function remove_plugin_capabilities() {
        $roles = ['administrator', 'editor'];
        $capabilities = ['kmul_manage_access', 'kmul_view_reports', 'kmul_export_data'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}
?>