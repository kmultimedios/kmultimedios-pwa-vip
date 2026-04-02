<?php
/**
 * PÁGINA INDEPENDIENTE PARA ENVIAR ADVERTENCIAS
 * Guardar como: /wp-content/plugins/kmultimedios-control-accesos-mejorado/warning-sender.php
 */

// Cargar WordPress
require_once('../../../wp-config.php');

// Verificar permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Procesar envío de advertencia
$mensaje_resultado = '';
$tipo_mensaje = '';

if ($_POST && isset($_POST['enviar_advertencia'])) {
    $username = sanitize_text_field($_POST['username']);
    
    if (empty($username)) {
        $mensaje_resultado = 'Por favor ingresa un nombre de usuario.';
        $tipo_mensaje = 'error';
    } else {
        // Verificar que el usuario existe
        $user = get_user_by('login', $username);
        
        if (!$user) {
            $mensaje_resultado = "El usuario '{$username}' no existe.";
            $tipo_mensaje = 'error';
        } else {
            // Enviar advertencia usando el sistema que funciona
            $to = $user->user_email;
            $subject = 'Advertencia - Múltiples dispositivos detectados';
            $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #ddd;'>
                <div style='background: #d63384; color: white; padding: 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px;'>⚠️ Advertencia de Seguridad</h1>
                </div>
                
                <div style='padding: 30px;'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Hola <strong>{$user->display_name}</strong>,</p>
                    
                    <div style='background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0; border-radius: 5px;'>
                        <p style='margin: 0; font-weight: bold; color: #856404;'>
                            🚨 Hemos detectado que tu cuenta ha iniciado sesión desde múltiples dispositivos.
                        </p>
                    </div>
                    
                    <p>Esto no está permitido según nuestros <strong>Términos y Condiciones de uso</strong>, específicamente en el apartado <em>Registro y Autenticación</em>, donde se establece que:</p>
                    
                    <blockquote style='background: #f8f9fa; padding: 15px; border-left: 4px solid #6c757d; margin: 20px 0; font-style: italic; color: #495057;'>
                        \"Está prohibido compartir cuentas o permitir que múltiples dispositivos no autorizados accedan con una misma suscripción. En caso de detectarse acceso irregular o compartido, la suscripción será cancelada inmediatamente sin derecho a reembolso.\"
                    </blockquote>
                    
                    <div style='background: #f8d7da; padding: 20px; border-left: 4px solid #dc3545; margin: 20px 0; border-radius: 5px;'>
                        <p style='margin: 0; font-weight: bold; color: #721c24;'>
                            🚫 Si continúas compartiendo tu acceso, tu cuenta será suspendida o cancelada automáticamente por el sistema.
                        </p>
                    </div>
                    
                    <p>Si crees que esto es un error o necesitas ayuda, por favor contacta a nuestro equipo de soporte.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <p style='color: #6c757d; font-size: 14px;'>
                            Este mensaje fue enviado automáticamente por el sistema de seguridad.<br>
                            <strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "<br>
                            <strong>Sitio:</strong> " . get_site_url() . "
                        </p>
                    </div>
                </div>
                
                <div style='background: #f8f9fa; padding: 15px; text-align: center; border-top: 1px solid #ddd;'>
                    <p style='margin: 0; color: #6c757d; font-size: 12px;'>
                        © " . date('Y') . " " . get_bloginfo('name') . " - Sistema de Control de Accesos
                    </p>
                </div>
            </div>
            ";
            
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Sistema de Seguridad <noreply@' . $_SERVER['HTTP_HOST'] . '>'
            ];
            
            $enviado = wp_mail($to, $subject, $message, $headers);
            
            if ($enviado) {
                // Registrar en base de datos si es posible
                global $wpdb;
                $table_warnings = $wpdb->prefix . 'kmul_warnings';
                
                $wpdb->insert(
                    $table_warnings,
                    [
                        'user_id' => $user->ID,
                        'username' => $username,
                        'email' => $user->user_email,
                        'fecha_advertencia' => current_time('mysql'),
                        'enviado_por' => get_current_user()->user_login
                    ]
                );
                
                $mensaje_resultado = "✅ Advertencia enviada exitosamente a <strong>{$username}</strong> ({$user->user_email})";
                $tipo_mensaje = 'success';
            } else {
                $mensaje_resultado = "❌ Error al enviar email a {$username}. Verifica la configuración de email.";
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener lista de usuarios recientes para autocompletar
$recent_users = get_users([
    'number' => 20,
    'meta_key' => 'last_activity',
    'orderby' => 'meta_value',
    'order' => 'DESC'
]);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Advertencia - Control de Accesos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f0f1;
            color: #1d2327;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d2327;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .user-suggestions {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .user-suggestions h4 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .user-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
        }
        
        .user-item {
            background: white;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid #e9ecef;
            transition: background-color 0.2s;
            font-size: 14px;
        }
        
        .user-item:hover {
            background: #e9ecef;
        }
        
        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .stats h3 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Enviar Advertencia</h1>
            <p>Sistema de Control de Accesos - Notificaciones por Email</p>
        </div>
        
        <div class="content">
            <a href="<?php echo admin_url('admin.php?page=kmultimedios-control-accesos'); ?>" class="back-link">
                ← Volver al Panel Principal
            </a>
            
            <?php
            // Mostrar estadísticas rápidas
            global $wpdb;
            $total_warnings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kmul_warnings");
            $today_warnings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kmul_warnings WHERE DATE(fecha_advertencia) = CURDATE()");
            $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
            ?>
            
            <div class="stats">
                <h3>📊 Estadísticas Rápidas</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_warnings); ?></div>
                        <div class="stat-label">Total Advertencias</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($today_warnings); ?></div>
                        <div class="stat-label">Hoy</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                </div>
            </div>
            
            <?php if ($mensaje_resultado): ?>
                <div class="alert <?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje_resultado; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">👤 Nombre de Usuario:</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Ingresa el nombre de usuario (ej: MasterMX)"
                        value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
                        autocomplete="off"
                        required
                    >
                </div>
                
                <button type="submit" name="enviar_advertencia" class="btn">
                    📧 Enviar Advertencia
                </button>
            </form>
            
            <?php if (!empty($recent_users)): ?>
                <div class="user-suggestions">
                    <h4>👥 Usuarios Recientes (Click para seleccionar):</h4>
                    <div class="user-list">
                        <?php foreach ($recent_users as $recent_user): ?>
                            <div class="user-item" onclick="document.getElementById('username').value='<?php echo esc_js($recent_user->user_login); ?>'">
                                <?php echo esc_html($recent_user->user_login); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Autocompletar con usuarios existentes mientras escribes
        document.getElementById('username').addEventListener('input', function() {
            // Aquí podrías agregar funcionalidad de autocompletado en tiempo real si lo deseas
        });
        
        // Confirmación antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            if (!confirm(`¿Estás seguro de enviar una advertencia a: ${username}?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>