<?php
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    die('No autorizado');
}

// Cargar las clases del plugin
require_once('includes/class-kmul-database.php');
require_once('includes/class-kmul-notifications.php');

$database = new KMUL_Database();
$notifications = new KMUL_Notifications($database);

$user = get_user_by('login', 'edlaredo');
if (!$user) {
    die('Usuario no encontrado');
}

$result = $notifications->send_warning_notification($user, 'multiple_devices', [
    'Acceso desde PC y dispositivo móvil',
    'Cambios de dispositivo muy frecuentes'
]);

echo "Advertencia enviada: " . ($result['success'] ? 'SI' : 'NO') . "<br>";
echo "Métodos: " . implode(', ', $result['methods']) . "<br>";
if (!empty($result['errors'])) {
    echo "Errores: " . implode(', ', $result['errors']);
}
?>