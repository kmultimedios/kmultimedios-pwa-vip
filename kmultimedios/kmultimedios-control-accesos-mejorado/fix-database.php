<?php
/**
 * Script temporal para recrear las tablas del plugin
 * Guarda esto como fix-database.php en la raíz del plugin y ejecuta desde el navegador
 */

// Asegurarse de que solo se ejecute desde WordPress
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('No tienes permisos para ejecutar este script');
}

global $wpdb;

echo "<h2>Reparando Base de Datos del Plugin KMUL</h2>";

// Eliminar tablas existentes si existen
$tables_to_drop = [
    $wpdb->prefix . 'kmul_device_logs',
    $wpdb->prefix . 'kmul_blocked_users', 
    $wpdb->prefix . 'kmul_warnings',
    $wpdb->prefix . 'kmul_settings'
];

echo "<h3>1. Eliminando tablas existentes...</h3>";
foreach ($tables_to_drop as $table) {
    $result = $wpdb->query("DROP TABLE IF EXISTS `$table`");
    echo "- Eliminada tabla: $table " . ($result !== false ? "✓" : "✗") . "<br>";
}

// Recrear tablas con estructura correcta
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$charset_collate = $wpdb->get_charset_collate();

echo "<h3>2. Recreando tablas...</h3>";

// Tabla de logs de dispositivos
$table_logs = $wpdb->prefix . 'kmul_device_logs';
$sql_logs = "CREATE TABLE {$table_logs} (
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

$result1 = dbDelta($sql_logs);
echo "- Tabla logs: " . ($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") ? "✓" : "✗") . "<br>";

// Tabla de usuarios bloqueados
$table_blocked = $wpdb->prefix . 'kmul_blocked_users';
$sql_blocked = "CREATE TABLE {$table_blocked} (
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

$result2 = dbDelta($sql_blocked);
echo "- Tabla blocked: " . ($wpdb->get_var("SHOW TABLES LIKE '$table_blocked'") ? "✓" : "✗") . "<br>";

// Tabla de advertencias
$table_warnings = $wpdb->prefix . 'kmul_warnings';
$sql_warnings = "CREATE TABLE {$table_warnings} (
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

$result3 = dbDelta($sql_warnings);
echo "- Tabla warnings: " . ($wpdb->get_var("SHOW TABLES LIKE '$table_warnings'") ? "✓" : "✗") . "<br>";

// Tabla de configuración
$table_settings = $wpdb->prefix . 'kmul_settings';
$sql_settings = "CREATE TABLE {$table_settings} (
    id int unsigned NOT NULL AUTO_INCREMENT,
    setting_name varchar(100) NOT NULL,
    setting_value longtext,
    setting_type varchar(20) NOT NULL DEFAULT 'string',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_setting (setting_name)
) $charset_collate;";

$result4 = dbDelta($sql_settings);
echo "- Tabla settings: " . ($wpdb->get_var("SHOW TABLES LIKE '$table_settings'") ? "✓" : "✗") . "<br>";

// Insertar configuración por defecto
echo "<h3>3. Insertando configuración por defecto...</h3>";
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
    $inserted = $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO {$table_settings} (setting_name, setting_value, setting_type) VALUES (%s, %s, %s)",
            $name,
            $config['value'],
            $config['type']
        )
    );
    echo "- Configuración $name: " . ($inserted !== false ? "✓" : "✗") . "<br>";
}

// Verificación final
echo "<h3>4. Verificación final...</h3>";
$verification_queries = [
    "SELECT COUNT(*) FROM {$table_logs}" => "Tabla logs accesible",
    "SELECT COUNT(*) FROM {$table_blocked} WHERE is_active = 1" => "Columna is_active presente",
    "SELECT COUNT(*) FROM {$table_warnings} WHERE sent_at > '2020-01-01'" => "Columna sent_at presente",
    "SELECT COUNT(*) FROM {$table_settings}" => "Configuración cargada"
];

foreach ($verification_queries as $query => $description) {
    $result = $wpdb->get_var($query);
    echo "- $description: " . ($result !== null ? "✓ ($result)" : "✗") . "<br>";
}

echo "<h3>5. ¡Reparación completada!</h3>";
echo "<p>Ya puedes volver al dashboard del plugin.</p>";
echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo (fix-database.php) después de usarlo.</p>";
?>
