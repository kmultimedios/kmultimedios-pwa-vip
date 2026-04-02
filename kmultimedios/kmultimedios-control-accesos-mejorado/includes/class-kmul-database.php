<?php
/**
 * Manejo seguro de la base de datos con prepared statements
 * 
 * @since 5.0.0
 */
class KMUL_Database {
    
    private $wpdb;
    private $table_logs;
    private $table_blocked;
    private $table_warnings;
    private $table_settings;
    
    const MAX_LOGS_DAYS = 90;
    const BATCH_SIZE = 1000;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_logs = $wpdb->prefix . 'kmul_device_logs';
        $this->table_blocked = $wpdb->prefix . 'kmul_blocked_users';
        $this->table_warnings = $wpdb->prefix . 'kmul_warnings';
        $this->table_settings = $wpdb->prefix . 'kmul_settings';
    }
    
    /**
     * Crear todas las tablas necesarias con índices optimizados
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $this->create_logs_table();
        $this->create_blocked_table();
        $this->create_warnings_table();
        $this->create_settings_table();
        
        // Insertar configuración por defecto
        $this->insert_default_settings();
    }
    
    private function create_logs_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            username varchar(100) NOT NULL,
            login_date datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            operating_system varchar(50) NOT NULL DEFAULT '',
            browser varchar(50) NOT NULL DEFAULT '',
            device_type varchar(20) NOT NULL DEFAULT '',
            user_agent text,
            access_count int unsigned NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_login_date (login_date),
            INDEX idx_ip_address (ip_address),
            INDEX idx_device_type (device_type),
            INDEX idx_last_seen (last_seen),
            UNIQUE KEY unique_user_device (user_id, ip_address, operating_system, browser, device_type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_blocked_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_blocked} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            username varchar(100) NOT NULL,
            reason text NOT NULL,
            blocked_by bigint(20) unsigned NOT NULL,
            blocked_at datetime NOT NULL,
            unblocked_at datetime NULL,
            unblocked_by bigint(20) unsigned NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_is_active (is_active),
            INDEX idx_blocked_at (blocked_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_warnings_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_warnings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            username varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            warning_type varchar(50) NOT NULL DEFAULT 'multiple_devices',
            sent_by bigint(20) unsigned NOT NULL,
            email_sent tinyint(1) NOT NULL DEFAULT 0,
            message_sent tinyint(1) NOT NULL DEFAULT 0,
            message_method varchar(50) NOT NULL DEFAULT '',
            warning_level tinyint(1) NOT NULL DEFAULT 1,
            sent_at datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_warning_type (warning_type),
            INDEX idx_sent_at (sent_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_settings_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_settings} (
            id int unsigned NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext,
            setting_type varchar(20) NOT NULL DEFAULT 'string',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (setting_name)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function insert_default_settings() {
        $default_settings = [
            'max_devices_per_user' => ['value' => 2, 'type' => 'int'],
            'max_ips_per_device' => ['value' => 3, 'type' => 'int'],
            'warning_enabled' => ['value' => 1, 'type' => 'boolean'],
            'auto_block_enabled' => ['value' => 0, 'type' => 'boolean'],
            'email_warnings' => ['value' => 1, 'type' => 'boolean'],
            'internal_messages' => ['value' => 1, 'type' => 'boolean'],
            'warning_threshold' => ['value' => 2, 'type' => 'int'],
            'cleanup_days' => ['value' => 90, 'type' => 'int']
        ];
        
        foreach ($default_settings as $name => $config) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "INSERT IGNORE INTO {$this->table_settings} (setting_name, setting_value, setting_type) VALUES (%s, %s, %s)",
                    $name,
                    $config['value'],
                    $config['type']
                )
            );
        }
    }
    
    /**
     * Insertar o actualizar registro de acceso de forma segura
     */
    public function log_user_access($user_data) {
        try {
            // Validar datos de entrada
            if (!$this->validate_user_data($user_data)) {
                return false;
            }
            
            // Buscar registro existente
            $existing = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, access_count FROM {$this->table_logs} 
                     WHERE user_id = %d AND ip_address = %s AND operating_system = %s 
                     AND browser = %s AND device_type = %s",
                    $user_data['user_id'],
                    $user_data['ip_address'],
                    $user_data['operating_system'],
                    $user_data['browser'],
                    $user_data['device_type']
                )
            );
            
            if ($existing) {
                // Actualizar registro existente
                return $this->wpdb->update(
                    $this->table_logs,
                    [
                        'access_count' => $existing->access_count + 1,
                        'last_seen' => current_time('mysql'),
                        'login_date' => $user_data['login_date']
                    ],
                    ['id' => $existing->id],
                    ['%d', '%s', '%s'],
                    ['%d']
                ) !== false;
            } else {
                // Insertar nuevo registro
                return $this->wpdb->insert(
                    $this->table_logs,
                    [
                        'user_id' => $user_data['user_id'],
                        'username' => $user_data['username'],
                        'login_date' => $user_data['login_date'],
                        'ip_address' => $user_data['ip_address'],
                        'operating_system' => $user_data['operating_system'],
                        'browser' => $user_data['browser'],
                        'device_type' => $user_data['device_type'],
                        'user_agent' => $user_data['user_agent'],
                        'last_seen' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                ) !== false;
            }
        } catch (Exception $e) {
            error_log('KMUL Error logging access: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estadísticas de usuario con paginación
     */
    public function get_user_statistics($page = 1, $per_page = 50) {
        $offset = ($page - 1) * $per_page;
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    username,
                    user_id,
                    COUNT(*) as total_records,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT CONCAT(operating_system, '-', device_type)) as device_combinations,
                    COUNT(DISTINCT device_type) as unique_devices,
                    SUM(access_count) as total_accesses,
                    MAX(last_seen) as last_access,
                    MIN(created_at) as first_access
                FROM {$this->table_logs} 
                WHERE last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY username, user_id 
                ORDER BY device_combinations DESC, unique_ips DESC, total_accesses DESC
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
    }
    
    /**
     * Obtener dispositivos de un usuario específico
     */
    public function get_user_devices($user_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_logs} 
                 WHERE user_id = %d 
                 ORDER BY last_seen DESC, access_count DESC",
                $user_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Verificar si un usuario está bloqueado
     */
    public function is_user_blocked($user_id) {
        $blocked = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_blocked} 
                 WHERE user_id = %d AND is_active = 1",
                $user_id
            )
        );
        
        return $blocked > 0;
    }
    
    /**
     * Bloquear usuario con registro de auditoría
     */
    public function block_user($user_id, $username, $reason, $blocked_by) {
        return $this->wpdb->insert(
            $this->table_blocked,
            [
                'user_id' => $user_id,
                'username' => $username,
                'reason' => $reason,
                'blocked_by' => $blocked_by,
                'blocked_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%d', '%s']
        ) !== false;
    }
    
    /**
     * Desbloquear usuario
     */
    public function unblock_user($user_id, $unblocked_by) {
        return $this->wpdb->update(
            $this->table_blocked,
            [
                'is_active' => 0,
                'unblocked_at' => current_time('mysql'),
                'unblocked_by' => $unblocked_by
            ],
            [
                'user_id' => $user_id,
                'is_active' => 1
            ],
            ['%d', '%s', '%d'],
            ['%d', '%d']
        ) !== false;
    }
    
    /**
     * Registrar advertencia enviada
     */
    public function log_warning($warning_data) {
        return $this->wpdb->insert(
            $this->table_warnings,
            [
                'user_id' => $warning_data['user_id'],
                'username' => $warning_data['username'],
                'email' => $warning_data['email'],
                'warning_type' => $warning_data['warning_type'] ?? 'multiple_devices',
                'sent_by' => $warning_data['sent_by'],
                'email_sent' => $warning_data['email_sent'],
                'message_sent' => $warning_data['message_sent'],
                'message_method' => $warning_data['message_method'] ?? '',
                'warning_level' => $warning_data['warning_level'] ?? 1,
                'sent_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s']
        ) !== false;
    }
    
    /**
     * Obtener usuarios bloqueados con paginación
     */
    public function get_blocked_users($page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT b.*, u.display_name, u.user_email 
                 FROM {$this->table_blocked} b
                 LEFT JOIN {$this->wpdb->users} u ON b.user_id = u.ID
                 WHERE b.is_active = 1
                 ORDER BY b.blocked_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
    }
    
    /**
     * Obtener advertencias enviadas con paginación
     */
    public function get_warnings($page = 1, $per_page = 50) {
        $offset = ($page - 1) * $per_page;
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT w.*, u.display_name 
                 FROM {$this->table_warnings} w
                 LEFT JOIN {$this->wpdb->users} u ON w.user_id = u.ID
                 ORDER BY w.sent_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
    }
    
    /**
     * Obtener configuración
     */
    public function get_setting($name, $default = null) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value, setting_type FROM {$this->table_settings} WHERE setting_name = %s",
                $name
            )
        );
        
        if (!$result) {
            return $default;
        }
        
        // Convertir según el tipo
        switch ($result->setting_type) {
            case 'int':
                return (int) $result->setting_value;
            case 'boolean':
                return (bool) $result->setting_value;
            case 'array':
                return maybe_unserialize($result->setting_value);
            default:
                return $result->setting_value;
        }
    }
    
    /**
     * Actualizar configuración
     */
    public function update_setting($name, $value, $type = 'string') {
        if ($type === 'array') {
            $value = maybe_serialize($value);
        }
        
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table_settings} (setting_name, setting_value, setting_type) 
                 VALUES (%s, %s, %s)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)",
                $name,
                $value,
                $type
            )
        ) !== false;
    }
    
    /**
     * Limpiar datos antiguos
     */
    public function cleanup_old_data() {
        $cleanup_days = $this->get_setting('cleanup_days', self::MAX_LOGS_DAYS);
        
        // Limpiar logs antiguos en lotes para evitar bloqueos de tabla
        $deleted_count = 0;
        do {
            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->table_logs} 
                     WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY) 
                     LIMIT %d",
                    $cleanup_days,
                    self::BATCH_SIZE
                )
            );
            $deleted_count += $deleted;
        } while ($deleted === self::BATCH_SIZE);
        
        error_log("KMUL: Cleaned up {$deleted_count} old access records");
        return $deleted_count;
    }
    
    /**
     * Validar datos de usuario antes de insertar
     */
    private function validate_user_data($data) {
        $required_fields = ['user_id', 'username', 'ip_address', 'login_date'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                error_log("KMUL: Missing required field: {$field}");
                return false;
            }
        }
        
        // Validar IP
        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            error_log("KMUL: Invalid IP address: {$data['ip_address']}");
            return false;
        }
        
        // Validar longitudes
        if (strlen($data['username']) > 100) {
            error_log("KMUL: Username too long");
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener contadores para dashboard
     */
    public function get_dashboard_counts() {
        $stats = [];
        
        // Usuarios bloqueados
        $stats['blocked_users'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_blocked} WHERE is_active = 1"
        );
        
        // Advertencias enviadas (últimos 30 días)
        $stats['recent_warnings'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_warnings} 
             WHERE sent_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Total de advertencias
        $stats['total_warnings'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_warnings}"
        );
        
        // Usuarios activos (últimos 7 días)
        $stats['active_users'] = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table_logs} 
             WHERE last_seen > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Obtener nombres de tablas (para uso externo)
     */
    public function get_table_names() {
        return [
            'logs' => $this->table_logs,
            'blocked' => $this->table_blocked,
            'warnings' => $this->table_warnings,
            'settings' => $this->table_settings
        ];
    }
}
?>