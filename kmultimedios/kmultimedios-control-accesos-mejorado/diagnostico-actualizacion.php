<?php
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    wp_die('No autorizado');
}

global $wpdb;

echo '<h2>Diagnóstico de Actualización del Plugin</h2>';

// 1. Verificar hooks de WordPress
echo '<h3>1. Verificar Hooks Registrados:</h3>';
$wp_login_hooks = array();
if (isset($GLOBALS['wp_filter']['wp_login'])) {
    foreach ($GLOBALS['wp_filter']['wp_login'] as $priority => $hooks) {
        foreach ($hooks as $hook) {
            if (is_array($hook['function']) && strpos(serialize($hook['function']), 'kmul') !== false) {
                echo '<p style="color: green;">✅ Hook de login encontrado en prioridad ' . $priority . '</p>';
            }
        }
    }
} else {
    echo '<p style="color: red;">❌ No hay hooks de wp_login registrados</p>';
}

// 2. Verificar tabla de logs
$table_logs = $wpdb->prefix . "kmul_device_logs";
echo '<h3>2. Últimos 5 registros de la base de datos:</h3>';
$recent_logs = $wpdb->get_results("SELECT * FROM $table_logs ORDER BY last_seen DESC LIMIT 5", ARRAY_A);

if ($recent_logs) {
    echo '<table border="1" style="border-collapse: collapse;"><tr style="background: #f0f0f0;">';
    echo '<th>Username</th><th>IP</th><th>Device</th><th>Last Seen</th><th>Access Count</th><th>Created At</th></tr>';
    
    foreach ($recent_logs as $log) {
        $time_diff = time() - strtotime($log['last_seen']);
        $hours_ago = round($time_diff / 3600, 1);
        
        echo '<tr>';
        echo '<td>' . esc_html($log['username']) . '</td>';
        echo '<td>' . esc_html($log['ip_address']) . '</td>';
        echo '<td>' . esc_html($log['device_type']) . '</td>';
        echo '<td>' . esc_html($log['last_seen']) . ' <small>(' . $hours_ago . ' horas)</small></td>';
        echo '<td>' . esc_html($log['access_count']) . '</td>';
        echo '<td>' . esc_html($log['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p style="color: red;">❌ No hay registros en la tabla de logs</p>';
}

// 3. Verificar sistema de tiempo
echo '<h3>3. Verificar Sistema de Tiempo:</h3>';
echo '<p><strong>Hora actual del servidor:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><strong>Zona horaria de WordPress:</strong> ' . get_option('timezone_string') . '</p>';
echo '<p><strong>current_time(mysql):</strong> ' . current_time('mysql') . '</p>';

// 4. Test manual de detección
echo '<h3>4. Test de Detección de Dispositivo:</h3>';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'No disponible';
echo '<p><strong>Tu User Agent:</strong> ' . esc_html($user_agent) . '</p>';

// 5. Verificar si el usuario actual está siendo rastreado
$current_user = wp_get_current_user();
if ($current_user->ID) {
    echo '<h3>5. Tu actividad registrada:</h3>';
    $my_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_logs WHERE username = %s ORDER BY last_seen DESC LIMIT 3",
        $current_user->user_login
    ), ARRAY_A);
    
    if ($my_logs) {
        foreach ($my_logs as $log) {
            echo '<p>Último acceso: ' . $log['last_seen'] . ' desde ' . $log['ip_address'] . ' (' . $log['device_type'] . ')</p>';
        }
    } else {
        echo '<p style="color: red;">❌ No tienes registros de acceso (problema grave)</p>';
    }
}

echo '<h3>6. Solución Recomendada:</h3>';
echo '<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;">';
echo 'Si no se están registrando nuevos accesos, el problema está en el hook de wp_login. ';
echo 'Necesitas verificar que el plugin se esté cargando correctamente y que los hooks estén funcionando.';
echo '</p>';
?>