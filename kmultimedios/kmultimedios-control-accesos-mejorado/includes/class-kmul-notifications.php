<?php
/**
 * Sistema de notificaciones y mensajería interno mejorado
 * 
 * @since 5.0.0
 */
class KMUL_Notifications {
    
    private $database;
    private $available_methods = [];
    
    public function __construct(KMUL_Database $database) {
        $this->database = $database;
        $this->detect_available_methods();
    }
    
    /**
     * Detectar métodos de mensajería disponibles
     */
    private function detect_available_methods() {
        // Ultimate Member
        if (function_exists('um_user_send_message')) {
            $this->available_methods['ultimate_member'] = [
                'name' => 'Ultimate Member',
                'function' => 'send_via_ultimate_member',
                'priority' => 1
            ];
        }
        
        // BuddyPress
        if (function_exists('messages_new_message')) {
            $this->available_methods['buddypress'] = [
                'name' => 'BuddyPress',
                'function' => 'send_via_buddypress',
                'priority' => 2
            ];
        }
        
        // WP User Frontend
        if (function_exists('wpuf_send_message')) {
            $this->available_methods['wpuf'] = [
                'name' => 'WP User Frontend',
                'function' => 'send_via_wpuf',
                'priority' => 3
            ];
        }
        
        // Sistema personalizado (siempre disponible)
        $this->available_methods['custom'] = [
            'name' => 'Sistema Personalizado',
            'function' => 'send_via_custom_system',
            'priority' => 99
        ];
    }
    
    /**
     * Enviar advertencia completa (email + mensaje interno)
     */
    public function send_warning_notification($user, $warning_type = 'multiple_devices', $risk_factors = []) {
        $results = [
            'success' => false,
            'methods' => [],
            'errors' => []
        ];
        
        // Enviar email
        $email_result = $this->send_email_warning($user, $warning_type, $risk_factors);
        if ($email_result['success']) {
            $results['methods'][] = 'Email';
        } else {
            $results['errors'][] = 'Email: ' . $email_result['error'];
        }
        
        // Enviar mensaje interno
        $message_result = $this->send_internal_message($user, $warning_type, $risk_factors);
        if ($message_result['success']) {
            $results['methods'][] = 'Mensaje interno (' . $message_result['method'] . ')';
        } else {
            $results['errors'][] = 'Mensaje: ' . $message_result['error'];
        }
        
        // Registrar advertencia en base de datos
        if (!empty($results['methods'])) {
            $this->database->log_warning([
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'warning_type' => $warning_type,
                'sent_by' => get_current_user_id(),
                'email_sent' => $email_result['success'] ? 1 : 0,
                'message_sent' => $message_result['success'] ? 1 : 0,
                'message_method' => $message_result['method'] ?? '',
                'warning_level' => $this->get_warning_level($risk_factors)
            ]);
            
            $results['success'] = true;
        }
        
        return $results;
    }
    
    /**
     * Enviar advertencia por email con plantilla profesional
     */
    private function send_email_warning($user, $warning_type, $risk_factors) {
        $template_data = $this->get_warning_template($warning_type, $risk_factors);
        
        $subject = $template_data['subject'];
        $message = $this->build_email_template($user, $template_data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        ];
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        return [
            'success' => $sent,
            'error' => $sent ? '' : 'Error al enviar email'
        ];
    }
    
    /**
     * Enviar mensaje interno usando método disponible
     */
    private function send_internal_message($user, $warning_type, $risk_factors) {
        // Ordenar métodos por prioridad
        uasort($this->available_methods, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        $template_data = $this->get_warning_template($warning_type, $risk_factors);
        $message_content = $this->build_text_message($user, $template_data);
        
        foreach ($this->available_methods as $method_key => $method_info) {
            $result = $this->{$method_info['function']}($user, $template_data['subject'], $message_content);
            
            if ($result) {
                return [
                    'success' => true,
                    'method' => $method_info['name']
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'No se pudo enviar por ningún método disponible'
        ];
    }
    
    /**
     * Obtener plantilla de advertencia según tipo
     */
    private function get_warning_template($warning_type, $risk_factors) {
        $templates = [
            'multiple_devices' => [
                'subject' => 'Múltiples dispositivos detectados en tu cuenta',
                'title' => 'MÚLTIPLES DISPOSITIVOS DETECTADOS',
                'icon' => '⚠️',
                'severity' => 'warning',
                'main_message' => 'Hemos detectado que tu cuenta ha iniciado sesión desde múltiples dispositivos diferentes.',
                'details' => $risk_factors,
                'action_required' => 'Si no reconoces algún dispositivo, cambia tu contraseña inmediatamente.'
            ],
            'suspicious_activity' => [
                'subject' => 'Actividad sospechosa detectada en tu cuenta',
                'title' => 'ACTIVIDAD SOSPECHOSA DETECTADA',
                'icon' => '🚨',
                'severity' => 'danger',
                'main_message' => 'Se han detectado patrones de acceso inusuales en tu cuenta.',
                'details' => $risk_factors,
                'action_required' => 'Revisa tu cuenta y cambia tu contraseña por seguridad.'
            ],
            'automatic' => [
                'subject' => 'Advertencia automática de seguridad',
                'title' => 'ADVERTENCIA AUTOMÁTICA',
                'icon' => '🔒',
                'severity' => 'info',
                'main_message' => 'Nuestro sistema de seguridad ha detectado un patrón que requiere tu atención.',
                'details' => $risk_factors,
                'action_required' => 'Verifica que todos los accesos a tu cuenta son legítimos.'
            ]
        ];
        
        return $templates[$warning_type] ?? $templates['multiple_devices'];
    }
    
    /**
     * Construir plantilla HTML para email
     */
    private function build_email_template($user, $template_data) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $user_name = $user->display_name ?: $user->user_login;
        
        $severity_colors = [
            'info' => ['bg' => '#d1ecf1', 'border' => '#17a2b8', 'text' => '#0c5460'],
            'warning' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'text' => '#856404'],
            'danger' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'text' => '#721c24']
        ];
        
        $colors = $severity_colors[$template_data['severity']] ?? $severity_colors['warning'];
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($template_data['subject']); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <!-- Header -->
                <div style="background: <?php echo $colors['border']; ?>; color: white; padding: 20px; text-align: center;">
                    <h1 style="margin: 0; font-size: 24px;"><?php echo $template_data['icon']; ?> <?php echo esc_html($site_name); ?></h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Sistema de Seguridad</p>
                </div>
                
                <!-- Alert Box -->
                <div style="background: <?php echo $colors['bg']; ?>; border-left: 4px solid <?php echo $colors['border']; ?>; color: <?php echo $colors['text']; ?>; padding: 20px; margin: 0;">
                    <h2 style="margin: 0 0 10px 0; font-size: 18px;"><?php echo esc_html($template_data['title']); ?></h2>
                    <p style="margin: 0; font-weight: bold;"><?php echo esc_html($template_data['main_message']); ?></p>
                </div>
                
                <!-- Content -->
                <div style="padding: 30px;">
                    <p><strong>Estimado/a <?php echo esc_html($user_name); ?>,</strong></p>
                    
                    <p><?php echo esc_html($template_data['main_message']); ?></p>
                    
                    <?php if (!empty($template_data['details'])): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">Detalles detectados:</h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($template_data['details'] as $detail): ?>
                            <li style="margin: 5px 0;"><?php echo esc_html($detail); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div style="background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">Acción requerida:</h4>
                        <p style="margin: 0;"><strong><?php echo esc_html($template_data['action_required']); ?></strong></p>
                    </div>
                    
                    <p>Si crees que esto es un error o necesitas ayuda, contacta a nuestro equipo de soporte.</p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($site_url); ?>" 
                           style="background: <?php echo $colors['border']; ?>; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                            Ir a mi cuenta
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px;">
                    <p style="margin: 0;">Este mensaje fue enviado automáticamente por el sistema de seguridad</p>
                    <p style="margin: 5px 0 0 0;">Fecha: <?php echo current_time('d/m/Y H:i:s'); ?></p>
                    <p style="margin: 10px 0 0 0;"><?php echo esc_html($site_name); ?> - <a href="<?php echo esc_url($site_url); ?>" style="color: #6c757d;"><?php echo esc_html($site_url); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Construir mensaje de texto para sistema interno
     */
    private function build_text_message($user, $template_data) {
        $site_name = get_bloginfo('name');
        $user_name = $user->display_name ?: $user->user_login;
        
        $message = $template_data['icon'] . " " . $template_data['title'] . "\n\n";
        $message .= "Estimado/a {$user_name},\n\n";
        $message .= $template_data['main_message'] . "\n\n";
        
        if (!empty($template_data['details'])) {
            $message .= "Detalles detectados:\n";
            foreach ($template_data['details'] as $detail) {
                $message .= "• " . $detail . "\n";
            }
            $message .= "\n";
        }
        
        $message .= "ACCIÓN REQUERIDA:\n";
        $message .= $template_data['action_required'] . "\n\n";
        $message .= "Si crees que esto es un error o necesitas ayuda, contacta a nuestro equipo de soporte.\n\n";
        $message .= "---\n";
        $message .= "Mensaje automático del sistema de seguridad\n";
        $message .= "Fecha: " . current_time('d/m/Y H:i:s') . "\n";
        $message .= $site_name;
        
        return $message;
    }
    
    /**
     * Enviar vía Ultimate Member
     */
    private function send_via_ultimate_member($user, $subject, $message) {
        try {
            if (function_exists('um_user_send_message')) {
                return um_user_send_message(get_current_user_id(), $user->ID, $subject, $message);
            }
        } catch (Exception $e) {
            error_log('KMUL: Ultimate Member error: ' . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Enviar vía BuddyPress
     */
    private function send_via_buddypress($user, $subject, $message) {
        try {
            if (function_exists('messages_new_message')) {
                $result = messages_new_message([
                    'sender_id' => get_current_user_id(),
                    'recipients' => [$user->ID],
                    'subject' => $subject,
                    'content' => $message
                ]);
                return $result !== false;
            }
        } catch (Exception $e) {
            error_log('KMUL: BuddyPress error: ' . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Enviar vía WPUF
     */
    private function send_via_wpuf($user, $subject, $message) {
        try {
            if (function_exists('wpuf_send_message')) {
                return wpuf_send_message($user->ID, $subject, $message, get_current_user_id());
            }
        } catch (Exception $e) {
            error_log('KMUL: WPUF error: ' . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Enviar vía sistema personalizado
     */
    private function send_via_custom_system($user, $subject, $message) {
        try {
            $message_data = [
                'id' => uniqid('kmul_msg_'),
                'subject' => $subject,
                'message' => $message,
                'sent_at' => current_time('mysql'),
                'sent_by' => get_current_user_id(),
                'read' => false,
                'type' => 'security_warning'
            ];
            
            // Obtener mensajes existentes
            $existing_messages = get_user_meta($user->ID, 'kmul_internal_messages', true);
            if (!is_array($existing_messages)) {
                $existing_messages = [];
            }
            
            // Agregar nuevo mensaje
            $existing_messages[] = $message_data;
            
            // Mantener solo los últimos 50 mensajes
            if (count($existing_messages) > 50) {
                $existing_messages = array_slice($existing_messages, -50);
            }
            
            // Guardar mensajes
            update_user_meta($user->ID, 'kmul_internal_messages', $existing_messages);
            
            // Crear notificación visible
            update_user_meta($user->ID, 'kmul_active_notification', [
                'message' => 'Tienes un nuevo mensaje importante del sistema de seguridad.',
                'type' => 'warning',
                'created_at' => current_time('mysql'),
                'show' => true
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('KMUL: Custom system error: ' . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Obtener nivel de advertencia basado en factores de riesgo
     */
    private function get_warning_level($risk_factors) {
        $factor_count = count($risk_factors);
        
        if ($factor_count >= 4) return 3; // Crítico
        if ($factor_count >= 2) return 2; // Alto
        return 1; // Normal
    }
    
    /**
     * Shortcode: Mostrar mensajes del usuario
     */
    public function display_user_messages($atts) {
        if (!is_user_logged_in()) {
            return '<p>Debes estar logueado para ver tus mensajes.</p>';
        }
        
        $current_user = wp_get_current_user();
        $messages = get_user_meta($current_user->ID, 'kmul_internal_messages', true);
        
        if (!is_array($messages) || empty($messages)) {
            return '<p>No tienes mensajes.</p>';
        }
        
        // Ordenar por fecha (más recientes primero)
        usort($messages, function($a, $b) {
            return strtotime($b['sent_at']) - strtotime($a['sent_at']);
        });
        
        ob_start();
        ?>
        <div class="kmul-user-messages">
            <h3>Mis Mensajes</h3>
            <?php foreach ($messages as $index => $message): ?>
                <div class="kmul-message <?php echo $message['read'] ? 'read' : 'unread'; ?>" 
                     style="background: <?php echo $message['read'] ? '#f8f9fa' : '#fff3cd'; ?>; 
                            border: 1px solid <?php echo $message['read'] ? '#dee2e6' : '#ffc107'; ?>; 
                            margin: 10px 0; padding: 15px; border-radius: 5px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0; color: #495057;">
                            <?php echo $message['read'] ? '📖' : '📩'; ?> 
                            <?php echo esc_html($message['subject']); ?>
                        </h4>
                        <small style="color: #6c757d;">
                            <?php echo human_time_diff(strtotime($message['sent_at']), current_time('timestamp')); ?> atrás
                        </small>
                    </div>
                    
                    <div style="white-space: pre-line; line-height: 1.6; margin-bottom: 10px;">
                        <?php echo esc_html($message['message']); ?>
                    </div>
                    
                    <?php if (!$message['read']): ?>
                        <button onclick="markMessageRead('<?php echo esc_js($message['id']); ?>')" 
                                style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                            ✓ Marcar como leído
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        function markMessageRead(messageId) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=kmul_mark_message_read&message_id=' + messageId + '&nonce=<?php echo wp_create_nonce('kmul_mark_message_read'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Mostrar notificaciones activas
     */
    public function display_notifications($atts) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $current_user = wp_get_current_user();
        $notification = get_user_meta($current_user->ID, 'kmul_active_notification', true);
        
        if (!is_array($notification) || !$notification['show']) {
            return '';
        }
        
        $type_colors = [
            'warning' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'text' => '#856404'],
            'danger' => ['bg' => '#f8d7da', 'border' => '#dc3545', 'text' => '#721c24'],
            'info' => ['bg' => '#d1ecf1', 'border' => '#17a2b8', 'text' => '#0c5460']
        ];
        
        $colors = $type_colors[$notification['type']] ?? $type_colors['warning'];
        
        ob_start();
        ?>
        <div class="kmul-notification" 
             style="background: <?php echo $colors['bg']; ?>; 
                    border: 1px solid <?php echo $colors['border']; ?>; 
                    color: <?php echo $colors['text']; ?>; 
                    padding: 15px; margin: 20px 0; border-radius: 5px; position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>⚠️ <?php echo esc_html($notification['message']); ?></strong>
                </div>
                <button onclick="dismissNotification()" 
                        style="background: <?php echo $colors['border']; ?>; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                    ✗ Cerrar
                </button>
            </div>
        </div>
        
        <script>
        function dismissNotification() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=kmul_dismiss_notification&nonce=<?php echo wp_create_nonce('kmul_dismiss_notification'); ?>'
            })
            .then(() => {
                document.querySelector('.kmul-notification').style.display = 'none';
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Marcar mensaje como leído
     */
    public function ajax_mark_message_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'kmul_mark_message_read')) {
            wp_send_json_error('Unauthorized');
        }
        
        $message_id = sanitize_text_field($_POST['message_id']);
        $user_id = get_current_user_id();
        
        $messages = get_user_meta($user_id, 'kmul_internal_messages', true);
        if (is_array($messages)) {
            foreach ($messages as &$message) {
                if ($message['id'] === $message_id) {
                    $message['read'] = true;
                    break;
                }
            }
            update_user_meta($user_id, 'kmul_internal_messages', $messages);
            wp_send_json_success();
        }
        
        wp_send_json_error('Message not found');
    }
    
    /**
     * AJAX: Descartar notificación
     */
    public function ajax_dismiss_notification() {
        if (!wp_verify_nonce($_POST['nonce'], 'kmul_dismiss_notification')) {
            wp_send_json_error('Unauthorized');
        }
        
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'kmul_active_notification', ['show' => false]);
        wp_send_json_success();
    }
}
?>