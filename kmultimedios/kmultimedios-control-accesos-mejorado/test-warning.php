<?php
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    die('No autorizado');
}

// Encontrar el usuario MasterMX
$user = get_user_by('login', 'MasterMX');
if (!$user) {
    die('Usuario no encontrado');
}

// Simular envío de advertencia por email
$to = $user->user_email;
$subject = 'Prueba - Múltiples dispositivos detectados';
$message = 'Prueba del sistema de advertencias';
$headers = ['Content-Type: text/html; charset=UTF-8'];

$enviado = wp_mail($to, $subject, $message, $headers);

echo "Email enviado: " . ($enviado ? "SI" : "NO");
?>