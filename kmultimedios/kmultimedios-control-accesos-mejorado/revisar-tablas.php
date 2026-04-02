<?php
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    wp_die('No autorizado');
}

global $wpdb;

echo '<h2>Diagnóstico de Tablas de Base de Datos</h2>';

// Revisar tabla de logs de dispositivos
$tabla_logs = $wpdb->prefix . "kmul_device_logs";
$estructura_logs = $wpdb->get_results("DESCRIBE $tabla_logs");

echo '<h3>Estructura de ' . $tabla_logs . ':</h3>';
if ($estructura_logs) {
    echo '<table border="1" style="border-collapse: collapse;"><tr style="background: #f0f0f0;"><th>Columna</th><th>Tipo</th><th>Null</th><th>Clave</th></tr>';
    foreach ($estructura_logs as $columna) {
        echo "<tr><td>{$columna->Field}</td><td>{$columna->Type}</td><td>{$columna->Null}</td><td>{$columna->Key}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<p style="color: red;">Tabla no existe</p>';
}

// Revisar tabla de usuarios bloqueados
$tabla_bloqueados = $wpdb->prefix . "kmul_blocked_users";
$estructura_bloqueados = $wpdb->get_results("DESCRIBE $tabla_bloqueados");

echo '<h3>Estructura de ' . $tabla_bloqueados . ':</h3>';
if ($estructura_bloqueados) {
    echo '<table border="1" style="border-collapse: collapse;"><tr style="background: #f0f0f0;"><th>Columna</th><th>Tipo</th><th>Null</th><th>Clave</th></tr>';
    foreach ($estructura_bloqueados as $columna) {
        echo "<tr><td>{$columna->Field}</td><td>{$columna->Type}</td><td>{$columna->Null}</td><td>{$columna->Key}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<p style="color: red;">Tabla no existe</p>';
}

// Revisar tabla de advertencias
$tabla_advertencias = $wpdb->prefix . "kmul_warnings";
$estructura_advertencias = $wpdb->get_results("DESCRIBE $tabla_advertencias");

echo '<h3>Estructura de ' . $tabla_advertencias . ':</h3>';
if ($estructura_advertencias) {
    echo '<table border="1" style="border-collapse: collapse;"><tr style="background: #f0f0f0;"><th>Columna</th><th>Tipo</th><th>Null</th><th>Clave</th></tr>';
    foreach ($estructura_advertencias as $columna) {
        echo "<tr><td>{$columna->Field}</td><td>{$columna->Type}</td><td>{$columna->Null}</td><td>{$columna->Key}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<p style="color: red;">Tabla no existe</p>';
}

echo '<h3>Datos de muestra (primeros 3 registros de logs):</h3>';
$datos_muestra = $wpdb->get_results("SELECT * FROM $tabla_logs LIMIT 3", ARRAY_A);
if ($datos_muestra) {
    echo '<pre style="background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';
    print_r($datos_muestra);
    echo '</pre>';
} else {
    echo '<p>No hay datos en la tabla de logs</p>';
}
?>