<?php
/**
 * Clase principal que coordina todas las funcionalidades del plugin
 * 
 * @since 5.0.0
 */
class KMUL_Core {
    
    private $database;
    private $detector;
    private $notifications;
    private $security;
    
    public function __construct(KMUL_Database $database, KMUL_Detector $detector, KMUL_Notifications $notifications, KMUL_Security $security) {
        $this->database = $database;
        $this->detector = $detector;
        $this->notifications = $notifications;
        $this->security = $security;
    }
    
    /**
     * Inicializar funcionalidades principales
     */
    public function init() {
        // Registrar shortcodes
        add_shortcode('kmul_user_messages', [$this->notifications, 'display_user_messages']);
        add_shortcode('kmul_user_notifications', [$this->notifications, 'display_notifications']);
        add_shortcode('kmul_user_device_info', [$this, 'display_user_device_info']);
        
        // Hooks para funcionalidad de mensajes
        add_action('wp_ajax_kmul_mark_message_read', [$this->notifications, 'ajax_mark_message_read']);
        add_action('wp_ajax_kmul_dismiss_notification', [$this->notifications, 'ajax_dismiss_notification']);
        
        // Hook para verificar límites de dispositivos en cada login
        add_action('wp_login', [$this, 'check_device_limits'], 20, 2);
    }
    
    /**
     * Manejar login de usuario con detección mejorada
     */
    public function handle_login($user_login, $user) {
        try {
            // Validar entrada
            if (!$user || !$user_login) {
                error_log('KMUL: Invalid login data received');
                return;
            }
            
            // Obtener información del dispositivo
            $device_info = $this->detector->detect_device_info(
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            );
            
            if (!$device_info) {
                error_log('KMUL: Could not detect device info for user: ' . $user_login);
                return;
            }
            
            // Preparar datos para log
            $user_data = [
                'user_id' => $user->ID,
                'username' => $user_login,
                'login_date' => current_time('mysql'),
                'ip_address' => $device_info['ip_address'],
                'operating_system' => $device_info['operating_system'],
                'browser' => $device_info['browser'],
                'device_type' => $device_info['device_type'],
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500) // Limitar longitud
            ];
            
            // Registrar acceso en base de datos
            $logged = $this->database->log_user_access($user_data);
            
            if (!$logged) {
                error_log('KMUL: Failed to log access for user: ' . $user_login);
            }
            
            // Verificar si es un bot
            if ($this->detector->detect_bot_patterns($_SERVER['HTTP_USER_AGENT'] ?? '')) {
                error_log('KMUL: Bot pattern detected for user: ' . $user_login);
                // Opcional: tomar acción contra bots
            }
            
        } catch (Exception $e) {
            error_log('KMUL Error in handle_login: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar límites de dispositivos después del login
     */
    public function check_device_limits($user_login, $user) {
        try {
            $user_devices = $this->database->get_user_devices($user->ID);
            
            if (empty($user_devices)) {
                return;
            }
            
            // Crear estadísticas simuladas para el análisis
            $user_stats = (object)[
                'user_id' => $user->ID,
                'username' => $user_login,
                'unique_devices' => count(array_unique(array_column($user_devices, 'device_type'))),
                'unique_ips' => count(array_unique(array_column($user_devices, 'ip_address'))),
                'total_records' => count($user_devices),
                'device_combinations' => count(array_unique(array_map(function($d) {
                    return $d['operating_system'] . '-' . $d['device_type'];
                }, $user_devices))),
                'total_accesses' => array_sum(array_column($user_devices, 'access_count'))
            ];
            
            $risk_analysis = $this->detector->analyze_user_risk($user_stats);
            
            // Acciones automáticas basadas en configuración
            $auto_warning = $this->database->get_setting('auto_warning_enabled', false);
            $auto_block = $this->database->get_setting('auto_block_enabled', false);
            
            if ($risk_analysis['risk_level'] === 'critical' && $auto_block) {
                $this->auto_block_user($user, 'Bloqueo automático por patrón crítico de riesgo');
            } elseif ($risk_analysis['risk_level'] === 'high' && $auto_warning) {
                $this->auto_send_warning($user, $risk_analysis);
            }
            
        } catch (Exception $e) {
            error_log('KMUL Error in check_device_limits: ' . $e->getMessage());
        }
    }
    
    /**
     * Bloquear usuario automáticamente
     */
    private function auto_block_user($user, $reason) {
        $blocked = $this->database->block_user(
            $user->ID,
            $user->user_login,
            $reason,
            0 // Sistema automático
        );
        
        if ($blocked) {
            wp_logout();
            wp_redirect(wp_login_url() . '?blocked=auto');
            exit;
        }
    }
    
    /**
     * Enviar advertencia automática
     */
    private function auto_send_warning($user, $risk_analysis) {
        // Verificar si ya se envió advertencia recientemente
        $recent_warning = $this->check_recent_warning($user->ID);
        if ($recent_warning) {
            return;
        }
        
        $warning_sent = $this->notifications->send_warning_notification(
            $user,
            'automatic',
            $risk_analysis['risk_factors']
        );
        
        if ($warning_sent) {
            error_log('KMUL: Automatic warning sent to user: ' . $user->user_login);
        }
    }
    
    /**
     * Verificar si se envió advertencia recientemente
     */
    private function check_recent_warning($user_id) {
        global $wpdb;
        $table_warnings = $this->database->get_table_names()['warnings'];
        
        $recent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_warnings} 
                 WHERE user_id = %d AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $user_id
            )
        );
        
        return $recent > 0;
    }
    
    /**
     * AJAX: Bloquear usuario
     */
    public function ajax_block_user() {
        // Verificar permisos y nonce
        if (!$this->security->verify_ajax_request()) {
            wp_send_json_error(['message' => 'No autorizado']);
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        $username = sanitize_text_field($_POST['username'] ?? '');
        $reason = sanitize_textarea_field($_POST['reason'] ?? 'Bloqueado por administrador');
        
        if (!$user_id || !$username) {
            wp_send_json_error(['message' => 'Datos de usuario inválidos']);
        }
        
        $blocked = $this->database->block_user(
            $user_id,
            $username,
            $reason,
            get_current_user_id()
        );
        
        if ($blocked) {
            // Forzar cierre de sesión del usuario si está conectado
            $this->force_user_logout($user_id);
            
            wp_send_json_success([
                'message' => 'Usuario bloqueado exitosamente',
                'user_id' => $user_id,
                'username' => $username
            ]);
        } else {
            wp_send_json_error(['message' => 'Error al bloquear usuario']);
        }
    }
    
    /**
     * AJAX: Desbloquear usuario
     */
    public function ajax_unblock_user() {
        if (!$this->security->verify_ajax_request()) {
            wp_send_json_error(['message' => 'No autorizado']);
        }
        
        $user_id = absint($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'ID de usuario inválido']);
        }
        
        $unblocked = $this->database->unblock_user($user_id, get_current_user_id());
        
        if ($unblocked) {
            wp_send_json_success([
                'message' => 'Usuario desbloqueado exitosamente',
                'user_id' => $user_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Error al desbloquear usuario']);
        }
    }
    
    /**
     * AJAX: Enviar advertencia
     */
public function ajax_send_warning() {
    // Verificar permisos
    if (!wp_verify_nonce($_POST['security'] ?? '', 'kmul_ajax_nonce')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sin permisos suficientes']);
    }
    
    // Obtener usuario
    $username = sanitize_text_field($_POST['username'] ?? '');
    
    if (empty($username)) {
        wp_send_json_error(['message' => 'Usuario no especificado']);
    }
    
    $user = get_user_by('login', $username);
    if (!$user) {
        wp_send_json_error(['message' => "Usuario {$username} no encontrado"]);
    }
    
    // Enviar email simple
    $to = $user->user_email;
    $subject = 'Advertencia - Múltiples dispositivos detectados';
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px;'>
        <h2 style='color: #d63384;'>⚠️ Advertencia de Seguridad</h2>
        <p>Hola <strong>{$user->display_name}</strong>,</p>
        <p style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>
            <strong>Hemos detectado que tu cuenta inició sesión en más de un dispositivo.</strong>
        </p>
        <p>Si esto continúa, tu cuenta será suspendida automáticamente.</p>
        <p>Si crees que esto es un error, contacta al soporte.</p>
        <hr>
        <p style='font-size: 12px; color: #6c757d;'>
            Fecha: " . date('d/m/Y H:i:s') . "
        </p>
    </div>
    ";
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $enviado = wp_mail($to, $subject, $message, $headers);
    
    if ($enviado) {
        wp_send_json_success([
            'message' => "✅ Advertencia enviada a {$username} ({$user->user_email}) correctamente"
        ]);
    } else {
        wp_send_json_error(['message' => "❌ Error al enviar email a {$username}"]);
    }
}
    
    /**
     * Forzar cierre de sesión de usuario específico
     */
    private function force_user_logout($user_id) {
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();
    }
    
    /**
     * Obtener análisis completo de usuarios para administrador
     */
    public function get_users_analysis($page = 1, $per_page = 50) {
        $users_stats = $this->database->get_user_statistics($page, $per_page);
        
        if (empty($users_stats)) {
            return [
                'users' => [],
                'classification' => ['critical' => [], 'high' => [], 'medium' => [], 'low' => []],
                'summary' => ['total' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0]
            ];
        }
        
        $classification = $this->detector->classify_users_by_risk($users_stats);
        
        // Agregar información adicional de usuario
        foreach ($classification as $level => &$users) {
            foreach ($users as &$user_data) {
                $wp_user = get_user_by('ID', $user_data['user_data']->user_id);
                $user_data['wp_user'] = $wp_user;
                $user_data['is_blocked'] = $this->database->is_user_blocked($user_data['user_data']->user_id);
            }
        }
        
        $summary = [
            'total' => count($users_stats),
            'critical' => count($classification['critical']),
            'high' => count($classification['high']),
            'medium' => count($classification['medium']),
            'low' => count($classification['low'])
        ];
        
        return [
            'users' => $users_stats,
            'classification' => $classification,
            'summary' => $summary
        ];
    }
    
    /**
     * Shortcode: Mostrar información de dispositivos del usuario
     */
    public function display_user_device_info($atts) {
        if (!is_user_logged_in()) {
            return '<p>Debes estar logueado para ver esta información.</p>';
        }
        
        $current_user = wp_get_current_user();
        $devices = $this->database->get_user_devices($current_user->ID);
        
        if (empty($devices)) {
            return '<p>No hay información de dispositivos registrada.</p>';
        }
        
        $output = '<div class="kmul-device-info">';
        $output .= '<h4>Tus dispositivos registrados:</h4>';
        $output .= '<div class="kmul-devices-grid" style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">';
        
        foreach (array_slice($devices, 0, 5) as $device) {
            $last_seen = human_time_diff(strtotime($device['last_seen']), current_time('timestamp'));
            
            $output .= '<div class="kmul-device-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
            $output .= '<h5 style="margin: 0 0 10px 0; color: #007cba;">' . esc_html($device['device_type']) . ' - ' . esc_html($device['operating_system']) . '</h5>';
            $output .= '<p style="margin: 5px 0;"><strong>Navegador:</strong> ' . esc_html($device['browser']) . '</p>';
            $output .= '<p style="margin: 5px 0;"><strong>IP:</strong> ' . esc_html($device['ip_address']) . '</p>';
            $output .= '<p style="margin: 5px 0;"><strong>Accesos:</strong> ' . esc_html($device['access_count']) . '</p>';
            $output .= '<p style="margin: 5px 0;"><strong>Último acceso:</strong> hace ' . $last_seen . '</p>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        // Mostrar advertencia si tiene múltiples dispositivos
        $unique_devices = array_unique(array_column($devices, 'device_type'));
        if (count($unique_devices) > 1) {
            $output .= '<div class="kmul-warning-notice" style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 5px;">';
            $output .= '<p style="margin: 0; color: #856404;"><strong>Aviso:</strong> Se han detectado múltiples tipos de dispositivos en tu cuenta. Si no reconoces algún dispositivo, contacta al soporte.</p>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Obtener estadísticas para dashboard
     */
    public function get_dashboard_statistics() {
        $counts = $this->database->get_dashboard_counts();
        $analysis = $this->get_users_analysis(1, 100); // Primeros 100 usuarios para resumen
        
        return [
            'blocked_users' => $counts['blocked_users'],
            'active_users' => $counts['active_users'],
            'total_warnings' => $counts['total_warnings'],
            'recent_warnings' => $counts['recent_warnings'],
            'risk_summary' => $analysis['summary'],
            'critical_users' => count($analysis['classification']['critical']),
            'high_risk_users' => count($analysis['classification']['high'])
        ];
    }
    
    /**
     * Exportar datos de análisis
     */
    public function export_analysis_data($format = 'csv') {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $analysis = $this->get_users_analysis(1, 1000); // Hasta 1000 usuarios
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($analysis);
            case 'json':
                return $this->export_to_json($analysis);
            default:
                return false;
        }
    }
    
    /**
     * Exportar a CSV
     */
    private function export_to_csv($analysis) {
        $filename = 'kmul_analysis_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers CSV
        fputcsv($output, [
            'Usuario',
            'Email',
            'Nivel de Riesgo',
            'Puntuación de Riesgo',
            'Dispositivos Únicos',
            'IPs Únicas',
            'Total Accesos',
            'Último Acceso',
            'Estado',
            'Factores de Riesgo'
        ]);
        
        // Datos
        foreach ($analysis['classification'] as $risk_level => $users) {
            foreach ($users as $user_data) {
                $user_stat = $user_data['user_data'];
                $risk = $user_data['risk_analysis'];
                $wp_user = $user_data['wp_user'];
                
                fputcsv($output, [
                    $user_stat->username,
                    $wp_user ? $wp_user->user_email : 'N/A',
                    ucfirst($risk_level),
                    $risk['risk_score'],
                    $user_stat->unique_devices,
                    $user_stat->unique_ips,
                    $user_stat->total_accesses,
                    $user_stat->last_access ?? 'N/A',
                    $user_data['is_blocked'] ? 'Bloqueado' : 'Activo',
                    implode('; ', $risk['risk_factors'])
                ]);
            }
        }
        
        fclose($output);
        return true;
    }
    
    /**
     * Exportar a JSON
     */
    private function export_to_json($analysis) {
        $filename = 'kmul_analysis_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $export_data = [
            'generated_at' => current_time('c'),
            'plugin_version' => KMUL_PLUGIN_VERSION,
            'summary' => $analysis['summary'],
            'users' => []
        ];
        
        foreach ($analysis['classification'] as $risk_level => $users) {
            foreach ($users as $user_data) {
                $user_stat = $user_data['user_data'];
                $risk = $user_data['risk_analysis'];
                $wp_user = $user_data['wp_user'];
                
                $export_data['users'][] = [
                    'username' => $user_stat->username,
                    'email' => $wp_user ? $wp_user->user_email : null,
                    'user_id' => $user_stat->user_id,
                    'risk_level' => $risk_level,
                    'risk_score' => $risk['risk_score'],
                    'risk_factors' => $risk['risk_factors'],
                    'statistics' => [
                        'unique_devices' => $user_stat->unique_devices,
                        'unique_ips' => $user_stat->unique_ips,
                        'total_accesses' => $user_stat->total_accesses,
                        'device_combinations' => $user_stat->device_combinations,
                        'last_access' => $user_stat->last_access ?? null
                    ],
                    'status' => $user_data['is_blocked'] ? 'blocked' : 'active'
                ];
            }
        }
        
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return true;
    }
    
    /**
     * Verificar integridad de datos
     */
    public function verify_data_integrity() {
        global $wpdb;
        $issues = [];
        
        $tables = $this->database->get_table_names();
        
        // Verificar existencia de tablas
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$exists) {
                $issues[] = "Tabla faltante: $name ($table)";
            }
        }
        
        // Verificar registros huérfanos
        $orphaned_logs = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['logs']} l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
             WHERE u.ID IS NULL"
        );
        
        if ($orphaned_logs > 0) {
            $issues[] = "Registros de logs huérfanos: $orphaned_logs";
        }
        
        // Verificar índices
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$tables['logs']}");
        $required_indexes = ['idx_user_id', 'idx_username', 'idx_login_date'];
        $existing_indexes = array_column($indexes, 'Key_name');
        
        foreach ($required_indexes as $required) {
            if (!in_array($required, $existing_indexes)) {
                $issues[] = "Índice faltante: $required en tabla logs";
            }
        }
        
        return [
            'status' => empty($issues) ? 'ok' : 'issues_found',
            'issues' => $issues,
            'checked_at' => current_time('c')
        ];
    }
    
    /**
     * Reparar problemas de integridad
     */
    public function repair_data_integrity() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        global $wpdb;
        $repairs = [];
        $tables = $this->database->get_table_names();
        
        // Limpiar registros huérfanos
        $deleted = $wpdb->query(
            "DELETE l FROM {$tables['logs']} l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
             WHERE u.ID IS NULL"
        );
        
        if ($deleted > 0) {
            $repairs[] = "Eliminados $deleted registros huérfanos";
        }
        
        // Recrear índices faltantes
        $indexes_created = 0;
        $required_indexes = [
            'idx_user_id' => 'ADD INDEX idx_user_id (user_id)',
            'idx_username' => 'ADD INDEX idx_username (username)',
            'idx_login_date' => 'ADD INDEX idx_login_date (login_date)',
            'idx_ip_address' => 'ADD INDEX idx_ip_address (ip_address)'
        ];
        
        foreach ($required_indexes as $name => $sql) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.statistics 
                     WHERE table_schema = %s AND table_name = %s AND index_name = %s",
                    DB_NAME,
                    $tables['logs'],
                    $name
                )
            );
            
            if (!$exists) {
                $result = $wpdb->query("ALTER TABLE {$tables['logs']} $sql");
                if ($result !== false) {
                    $indexes_created++;
                }
            }
        }
        
        if ($indexes_created > 0) {
            $repairs[] = "Recreados $indexes_created índices";
        }
        
        return [
            'status' => 'completed',
            'repairs' => $repairs,
            'repaired_at' => current_time('c')
        ];
    }
}
?>