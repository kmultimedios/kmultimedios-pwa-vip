<?php
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    wp_die('No autorizado');
}

echo '<h2>Verificación de Hook</h2>';

// Verificar si el hook está registrado
global $wp_filter;
if (isset($wp_filter['wp_login'])) {
    echo '<p style="color: green;">✅ wp_login hook está registrado</p>';
    foreach ($wp_filter['wp_login'] as $priority => $hooks) {
        foreach ($hooks as $hook) {
            if (is_string($hook['function']) && $hook['function'] === 'kmul_capturar_login') {
                echo '<p style="color: green;">✅ kmul_capturar_login encontrado</p>';
            }
        }
    }
} else {
    echo '<p style="color: red;">❌ wp_login hook NO está registrado</p>';
}

// Verificar si la función existe
if (function_exists('kmul_capturar_login')) {
    echo '<p style="color: green;">✅ Función kmul_capturar_login existe</p>';
} else {
    echo '<p style="color: red;">❌ Función kmul_capturar_login NO existe</p>';
}
?>