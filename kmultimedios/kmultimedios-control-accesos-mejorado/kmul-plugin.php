<?php
/**
 * Plugin Name: KMultimedios - Control de Accesos Corregido
 * Description: Plugin corregido para funcionar con la estructura de base de datos existente
 * Version: 4.7 - Sintaxis Corregida
 * Author: Edgar Laredo
 */

if (!defined('ABSPATH')) exit;

// Menú de administración
add_action('admin_menu', function () {
    add_menu_page(
        'Control de Accesos', 
        'Control de Accesos', 
        'manage_options', 
        'control_accesos_km', 
        'kmul_control_accesos_page', 
        'dashicons-shield-alt', 
        91
    );
    
    add_submenu_page(
        'control_accesos_km',
        'Usuarios Bloqueados',
        'Usuarios Bloqueados',
        'manage_options',
        'kmul_blocked_users',
        'kmul_blocked_users_page'
    );
    
    add_submenu_page(
        'control_accesos_km',
        'Advertencias Enviadas',
        'Advertencias Enviadas',
        'manage_options',
        'kmul_warnings',
        'kmul_warnings_page'
    );

    add_submenu_page(
        'control_accesos_km',
        'Enviar Advertencia',
        '📧 Enviar Advertencia',
        'manage_options',
        'kmul_send_warning',
        'kmul_send_warning_page'
    );
});

function kmul_control_accesos_page() {
    global $wpdb;
    
    // Procesar acciones
    if (isset($_POST['kmul_action']) && wp_verify_nonce($_POST['kmul_nonce'], 'kmul_action')) {
        kmul_procesar_accion();
    }
    
    // Obtener análisis de usuarios con la estructura correcta
    $analisis = kmul_analizar_usuarios_real();
    $todos_registros = array_merge($analisis['normales'], $analisis['sospechosos'], $analisis['infractores']);
    
    ?>
    <div class="wrap">
        <h1>🛡️ Control de Accesos - Información Completa</h1>
        
        <style>
            .kmul-infractor { background-color: #f8d7da !important; border-left: 5px solid #dc3545; }
            .kmul-suspicious { background-color: #fff3cd !important; border-left: 5px solid #ffc107; }
            .kmul-warning { background-color: #d1ecf1 !important; border-left: 5px solid #17a2b8; }
            .kmul-normal { background-color: #d4edda !important; border-left: 5px solid #28a745; }
            .kmul-blocked { background-color: #f8d7da !important; text-decoration: line-through; opacity: 0.7; }
            
            .kmul-user-group { margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; }
            .kmul-user-header { background: #f8f9fa; padding: 15px; font-weight: bold; cursor: pointer; }
            .kmul-user-header:hover { background: #e9ecef; }
            .kmul-devices-container { padding: 0; }
            .kmul-device-row { border-bottom: 1px solid #eee; padding: 8px; }
            .kmul-device-row:hover { background: #f8f9fa; }
            
            .kmul-stats { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
            .kmul-stat-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-width: 150px; }
            
            .kmul-ip-cell { font-family: monospace; font-size: 12px; color: #666; }
            .kmul-device-detail { font-size: 13px; padding: 4px 0; }
            .kmul-connection-info { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 4px; }
            
            @media (max-width: 768px) {
                .kmul-stats { flex-direction: column; }
                .kmul-user-header div { float: none !important; margin-top: 10px; }
            }
        </style>

        <!-- Estadísticas -->
        <div class="kmul-stats">
            <div class="kmul-stat-box" style="border-left: 5px solid #dc3545;">
                <h3>🚨 Infractores</h3>
                <p style="font-size: 2rem; margin: 0; color: #dc3545;"><strong><?php echo count($analisis['infractores']); ?></strong></p>
                <small>PC + Móvil o múltiples SO</small>
            </div>
            <div class="kmul-stat-box" style="border-left: 5px solid #ffc107;">
                <h3>⚠️ Sospechosos</h3>
                <p style="font-size: 2rem; margin: 0; color: #ffc107;"><strong><?php echo count($analisis['sospechosos']); ?></strong></p>
                <small>Múltiples IPs o patrones raros</small>
            </div>
            <div class="kmul-stat-box" style="border-left: 5px solid #28a745;">
                <h3>✅ Normales</h3>
                <p style="font-size: 2rem; margin: 0; color: #28a745;"><strong><?php echo count($analisis['normales']); ?></strong></p>
                <small>Uso estándar</small>
            </div>
            <div class="kmul-stat-box" style="border-left: 5px solid #6c757d;">
                <h3>📱 Total Usuarios</h3>
                <p style="font-size: 2rem; margin: 0; color: #6c757d;"><strong><?php echo count($todos_registros); ?></strong></p>
                <small>Usuarios activos</small>
            </div>
            <div class="kmul-stat-box" style="border-left: 5px solid #dc3545;">
                <h3>🚫 Bloqueados</h3>
                <p style="font-size: 2rem; margin: 0; color: #dc3545;"><strong><?php echo kmul_contar_bloqueados(); ?></strong></p>
                <small><a href="<?php echo admin_url('admin.php?page=kmul_blocked_users'); ?>" style="color: #dc3545;">Ver todos</a></small>
            </div>
            <div class="kmul-stat-box" style="border-left: 5px solid #ffc107;">
                <h3>📧 Advertencias</h3>
                <p style="font-size: 2rem; margin: 0; color: #ffc107;"><strong><?php echo kmul_contar_advertencias(); ?></strong></p>
                <small><a href="<?php echo admin_url('admin.php?page=kmul_warnings'); ?>" style="color: #ffc107;">Ver todas</a></small>
            </div>
        </div>

        <!-- Controles -->
        <div style="margin: 20px 0;">
            <button onclick="expandirTodos()" class="button">📂 Expandir Todos</button>
            <button onclick="contraerTodos()" class="button">📁 Contraer Todos</button>
            <button onclick="filtrarPorTipo('infractores')" class="button" style="background: #dc3545; color: white;">🚨 Solo Infractores</button>
            <button onclick="filtrarPorTipo('sospechosos')" class="button" style="background: #ffc107;">⚠️ Solo Sospechosos</button>
            <button onclick="filtrarPorTipo('todos')" class="button">👥 Todos</button>
            <a href="<?php echo admin_url('admin.php?page=kmul_send_warning'); ?>" class="button" style="background: #28a745; color: white; text-decoration: none;">📧 Enviar Advertencia</a>
        </div>

        <!-- INFRACTORES -->
        <?php if (!empty($analisis['infractores'])): ?>
        <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 15px; margin: 20px 0 10px 0; border-radius: 8px;">
            🚨 INFRACTORES - ACCIÓN INMEDIATA REQUERIDA (<?php echo count($analisis['infractores']); ?>)
        </div>
        <div class="kmul-infractores-section">
        <?php foreach ($analisis['infractores'] as $username => $dispositivos): ?>
            <?php kmul_mostrar_grupo_usuario_real($username, $dispositivos, 'infractor'); ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- SOSPECHOSOS -->
        <?php if (!empty($analisis['sospechosos'])): ?>
        <div style="background: linear-gradient(135deg, #ffc107, #e0a800); color: #000; padding: 15px; margin: 20px 0 10px 0; border-radius: 8px;">
            ⚠️ USUARIOS SOSPECHOSOS - REVISAR (<?php echo count($analisis['sospechosos']); ?>)
        </div>
        <div class="kmul-sospechosos-section">
        <?php foreach ($analisis['sospechosos'] as $username => $dispositivos): ?>
            <?php kmul_mostrar_grupo_usuario_real($username, $dispositivos, 'sospechoso'); ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- USUARIOS NORMALES -->
        <div style="background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 15px; margin: 20px 0 10px 0; border-radius: 8px;">
            ✅ USUARIOS NORMALES (<?php echo count($analisis['normales']); ?>)
        </div>
        <div class="kmul-normales-section" style="display: none;">
        <?php foreach ($analisis['normales'] as $username => $dispositivos): ?>
            <?php kmul_mostrar_grupo_usuario_real($username, $dispositivos, 'normal'); ?>
        <?php endforeach; ?>
        </div>
        
        <button onclick="toggleSection('normales')" class="button" style="margin-top: 10px;">
            👁️ Mostrar/Ocultar Usuarios Normales
        </button>
    </div>

    <script>
    function toggleUserDetails(userId) {
        var container = document.getElementById(userId);
        var icon = document.getElementById('icon-' + userId);
        
        if (container.style.display === 'none') {
            container.style.display = 'block';
            icon.textContent = '▼';
        } else {
            container.style.display = 'none';
            icon.textContent = '▶️';
        }
    }

    function expandirTodos() {
        var containers = document.querySelectorAll('.kmul-devices-container');
        var icons = document.querySelectorAll('.kmul-toggle-icon');
        
        containers.forEach(function(container) {
            container.style.display = 'block';
        });
        
        icons.forEach(function(icon) {
            icon.textContent = '▼';
        });
    }

    function contraerTodos() {
        var containers = document.querySelectorAll('.kmul-devices-container');
        var icons = document.querySelectorAll('.kmul-toggle-icon');
        
        containers.forEach(function(container) {
            container.style.display = 'none';
        });
        
        icons.forEach(function(icon) {
            icon.textContent = '▶️';
        });
    }

    function toggleSection(tipo) {
        var section = document.querySelector('.kmul-' + tipo + '-section');
        if (section.style.display === 'none') {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    }

    function filtrarPorTipo(tipo) {
        var infractores = document.querySelector('.kmul-infractores-section');
        var sospechosos = document.querySelector('.kmul-sospechosos-section');
        var normales = document.querySelector('.kmul-normales-section');
        
        if (infractores) infractores.style.display = 'none';
        if (sospechosos) sospechosos.style.display = 'none';
        if (normales) normales.style.display = 'none';
        
        switch(tipo) {
            case 'infractores':
                if (infractores) infractores.style.display = 'block';
                break;
            case 'sospechosos':
                if (sospechosos) sospechosos.style.display = 'block';
                break;
            case 'todos':
                if (infractores) infractores.style.display = 'block';
                if (sospechosos) sospechosos.style.display = 'block';
                break;
        }
    }

    function bloquearUsuario(username) {
        if (confirm('¿Estás seguro de bloquear al usuario: ' + username + '?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="kmul_action" value="bloquear">' +
                           '<input type="hidden" name="username" value="' + username + '">' +
                           '<input type="hidden" name="kmul_nonce" value="<?php echo wp_create_nonce('kmul_action'); ?>">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    function desbloquearUsuario(username) {
        if (confirm('¿Estás seguro de desbloquear al usuario: ' + username + '?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="kmul_action" value="desbloquear">' +
                           '<input type="hidden" name="username" value="' + username + '">' +
                           '<input type="hidden" name="kmul_nonce" value="<?php echo wp_create_nonce('kmul_action'); ?>">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    function advertirUsuario(username) {
        if (confirm('¿Enviar advertencia automática a ' + username + '?\n\nSe enviará por email el mensaje de advertencia por múltiples dispositivos.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="kmul_action" value="advertir">' +
                           '<input type="hidden" name="username" value="' + username + '">' +
                           '<input type="hidden" name="kmul_nonce" value="<?php echo wp_create_nonce('kmul_action'); ?>">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
    <?php
}

// FUNCIÓN PRINCIPAL: Mostrar información COMPLETA del usuario usando estructura real de BD
function kmul_mostrar_grupo_usuario_real($username, $dispositivos, $nivel_riesgo) {
    if (empty($dispositivos)) return;
    
    $total_dispositivos = count($dispositivos);
    $total_accesos = array_sum(array_column($dispositivos, 'access_count'));
    $ips_unicas = array_unique(array_column($dispositivos, 'ip_address'));
    $total_ips = count($ips_unicas);
    $usuario_bloqueado = kmul_usuario_esta_bloqueado($username);
    
    // Determinar clase, icono y texto según nivel de riesgo
    $clase_usuario = '';
    $icono = '';
    $texto_estado = '';
    
    if ($usuario_bloqueado) {
        $clase_usuario = 'kmul-blocked';
        $icono = '🚫';
        $texto_estado = 'BLOQUEADO';
    } else {
        switch ($nivel_riesgo) {
            case 'infractor':
                $clase_usuario = 'kmul-infractor';
                $icono = '🚨';
                $texto_estado = 'INFRACTOR';
                break;
            case 'sospechoso':
                $clase_usuario = 'kmul-suspicious';
                $icono = '⚠️';
                $texto_estado = 'SOSPECHOSO';
                break;
            case 'multiple':
                $clase_usuario = 'kmul-warning';
                $icono = '📱';
                $texto_estado = 'MÚLTIPLES IPs';
                break;
            default:
                $clase_usuario = 'kmul-normal';
                $icono = '✅';
                $texto_estado = 'NORMAL';
        }
    }
    
    $user_id = 'user-' . sanitize_title($username);
    
    // Enlaces a perfil WP
    $wp_user = get_user_by('login', $username);
    $edit_link = $wp_user ? get_edit_user_link($wp_user->ID) : '';
    ?>

    <div class="kmul-user-group <?php echo $clase_usuario; ?>">
        <div class="kmul-user-header" onclick="toggleUserDetails('<?php echo $user_id; ?>')">
            <span class="kmul-toggle-icon" id="icon-<?php echo $user_id; ?>">▶️</span>
            <?php echo $icono; ?> 
            <?php if ($wp_user && $edit_link): ?>
            <a href="<?php echo esc_url($edit_link); ?>" onclick="event.stopPropagation();" title="Perfil del usuario">
                <strong><?php echo esc_html($username); ?></strong>
            </a>
            <?php else: ?>
            <strong><?php echo esc_html($username); ?></strong>
            <?php endif; ?>
            
            - <strong><?php echo $total_dispositivos; ?> conexión(es)</strong> 
            - <strong><?php echo $total_ips; ?> IP(s) diferentes</strong>
            - <strong><?php echo $total_accesos; ?> accesos totales</strong>
            
            <?php if ($texto_estado !== 'NORMAL'): ?>
                <span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;"> <?php echo $texto_estado; ?></span>
            <?php endif; ?>
            
            <div style="float: right;" onclick="event.stopPropagation();">
                <?php if ($usuario_bloqueado): ?>
                    <button onclick="desbloquearUsuario('<?php echo esc_js($username); ?>')" 
                            class="button button-small" style="background: #28a745; color: white;">
                        🔓 Desbloquear
                    </button>
                    <span style="color: #d63384; font-weight: bold;">🚫 BLOQUEADO</span>
                <?php else: ?>
                    <button onclick="bloquearUsuario('<?php echo esc_js($username); ?>')" 
                            class="button button-small" style="background: #dc3545; color: white;">
                        ⛔ Bloquear
                    </button>
                    <button onclick="advertirUsuario('<?php echo esc_js($username); ?>')" 
                            class="button button-small button-secondary">
                        ⚠️ Advertir
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- INFORMACIÓN DETALLADA DE CADA CONEXIÓN -->
        <div class="kmul-devices-container" id="<?php echo $user_id; ?>" style="display: none; padding: 15px;">
            
            <!-- Resumen de IPs -->
            <div class="kmul-connection-info">
                <h4>📍 IPs Detectadas (<?php echo $total_ips; ?>):</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                    <?php foreach ($ips_unicas as $ip): ?>
                        <div class="kmul-ip-cell" style="background: #f0f0f0; padding: 8px; border-radius: 4px;">
                            <strong><?php echo esc_html($ip); ?></strong>
                            <?php 
                            $conexiones_ip = array_filter($dispositivos, function($d) use ($ip) { 
                                return $d['ip_address'] === $ip; 
                            });
                            echo '<br><small>' . count($conexiones_ip) . ' conexión(es)</small>';
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tabla detallada de conexiones -->
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px;"><strong>🌐 IP Address</strong></th>
                        <th style="padding: 12px;"><strong>💻 Sistema Operativo</strong></th>
                        <th style="padding: 12px;"><strong>🌍 Navegador</strong></th>
                        <th style="padding: 12px;"><strong>📱 Tipo Dispositivo</strong></th>
                        <th style="padding: 12px;"><strong>🕒 Último Acceso</strong></th>
                        <th style="padding: 12px;"><strong>🔢 Accesos</strong></th>
                        <th style="padding: 12px;"><strong>📊 Estado</strong></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dispositivos as $dispositivo): ?>
                    <tr class="kmul-device-row">
                        <td style="padding: 10px;">
                            <div class="kmul-ip-cell">
                                <strong><?php echo esc_html($dispositivo['ip_address']); ?></strong>
                                <br><small style="color: #666;">
                                    <?php 
                                    // Mostrar si es IP local o externa
                                    if (strpos($dispositivo['ip_address'], '192.168.') === 0 || 
                                        strpos($dispositivo['ip_address'], '10.') === 0 || 
                                        strpos($dispositivo['ip_address'], '172.') === 0) {
                                        echo '🏠 IP Local';
                                    } else {
                                        echo '🌍 IP Externa';
                                    }
                                    ?>
                                </small>
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <div class="kmul-device-detail">
                                <strong><?php echo esc_html($dispositivo['operating_system']); ?></strong>
                                <?php
                                if (strpos(strtolower($dispositivo['operating_system']), 'windows') !== false) echo '<br>🪟';
                                elseif (strpos(strtolower($dispositivo['operating_system']), 'mac') !== false) echo '<br>🍎';
                                elseif (strpos(strtolower($dispositivo['operating_system']), 'android') !== false) echo '<br>🤖';
                                elseif (strpos(strtolower($dispositivo['operating_system']), 'ios') !== false || strpos(strtolower($dispositivo['operating_system']), 'iphone') !== false) echo '<br>📱';
                                ?>
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <div class="kmul-device-detail">
                                <strong><?php echo esc_html($dispositivo['browser']); ?></strong>
                                <?php
                                $nav = strtolower($dispositivo['browser']);
                                if (strpos($nav, 'chrome') !== false) echo '<br>🟢';
                                elseif (strpos($nav, 'firefox') !== false) echo '<br>🟠';
                                elseif (strpos($nav, 'safari') !== false) echo '<br>🔵';
                                elseif (strpos($nav, 'edge') !== false) echo '<br>🔷';
                                ?>
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <div class="kmul-device-detail">
                                <strong><?php echo esc_html($dispositivo['device_type']); ?></strong>
                                <?php
                                if ($dispositivo['device_type'] === 'PC') echo '<br>🖥️';
                                elseif ($dispositivo['device_type'] === 'Mobile') echo '<br>📱';
                                elseif ($dispositivo['device_type'] === 'Tablet') echo '<br>📱';
                                ?>
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <div class="kmul-device-detail">
                                <strong><?php echo date('d/m/Y', strtotime($dispositivo['last_seen'])); ?></strong>
                                <br><small><?php echo date('H:i:s', strtotime($dispositivo['last_seen'])); ?></small>
                                <br><small style="color: #666;">
                                    <?php
                                    $diff = time() - strtotime($dispositivo['last_seen']);
                                    if ($diff < 3600) echo 'Hace ' . round($diff/60) . ' min';
                                    elseif ($diff < 86400) echo 'Hace ' . round($diff/3600) . ' horas';
                                    else echo 'Hace ' . round($diff/86400) . ' días';
                                    ?>
                                </small>
                            </div>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <div style="font-size: 18px; font-weight: bold; color: #007cba;">
                                <?php echo esc_html($dispositivo['access_count']); ?>
                            </div>
                            <small style="color: #666;">accesos</small>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php
                            if ($dispositivo['access_count'] > 100) {
                                echo '<span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">🔴 Muy Alto</span>';
                            } elseif ($dispositivo['access_count'] > 50) {
                                echo '<span style="background: #fd7e14; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">🟠 Alto</span>';
                            } elseif ($dispositivo['access_count'] > 10) {
                                echo '<span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">🟡 Moderado</span>';
                            } elseif ($dispositivo['access_count'] > 5) {
                                echo '<span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">🟢 Normal</span>';
                            } else {
                                echo '<span style="background: #6c757d; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">⚪ Nuevo</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Análisis de riesgo detallado -->
            <div class="kmul-connection-info" style="margin-top: 15px;">
                <h4>🔍 Análisis de Riesgo:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                    <div style="background: #e9ecef; padding: 10px; border-radius: 4px;">
                        <strong>Tipos de dispositivos:</strong><br>
                        <?php
                        $tipos_dispositivos = array_unique(array_column($dispositivos, 'device_type'));
                        foreach ($tipos_dispositivos as $tipo) {
                            echo '<span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.8rem; margin: 2px;">' . $tipo . '</span> ';
                        }
                        ?>
                    </div>
                    <div style="background: #e9ecef; padding: 10px; border-radius: 4px;">
                        <strong>Sistemas operativos:</strong><br>
                        <?php
                        $sistemas = array_unique(array_column($dispositivos, 'operating_system'));
                        foreach ($sistemas as $so) {
                            echo '<span style="background: #6f42c1; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.8rem; margin: 2px;">' . $so . '</span> ';
                        }
                        ?>
                    </div>
                    <div style="background: #e9ecef; padding: 10px; border-radius: 4px;">
                        <strong>Navegadores:</strong><br>
                        <?php
                        $navegadores = array_unique(array_column($dispositivos, 'browser'));
                        foreach ($navegadores as $nav) {
                            echo '<span style="background: #20c997; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.8rem; margin: 2px;">' . $nav . '</span> ';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// FUNCIÓN: Análisis de usuarios usando la estructura REAL de tu base de datos
function kmul_analizar_usuarios_real() {
    global $wpdb;
    
    $table_logs = $wpdb->prefix . "kmul_device_logs";
    
    // Query corregida usando los nombres reales de columnas
    $query = "
        SELECT username, 
               COUNT(*) as total_registros,
               COUNT(DISTINCT ip_address) as ips_diferentes,
               COUNT(DISTINCT CONCAT(operating_system, '-', device_type)) as tipos_dispositivos,
               COUNT(DISTINCT device_type) as dispositivos_diferentes,
               SUM(access_count) as total_accesos,
               MAX(last_seen) as ultimo_acceso
        FROM $table_logs 
        GROUP BY username 
        ORDER BY tipos_dispositivos DESC, ips_diferentes DESC, total_accesos DESC
    ";
    
    $usuarios = $wpdb->get_results($query);
    if (empty($usuarios)) {
        return ['infractores' => [], 'sospechosos' => [], 'normales' => []];
    }
    
    $infractores = [];
    $sospechosos = [];
    $normales = [];
    
    foreach ($usuarios as $user) {
        // Obtener TODOS los dispositivos usando nombres reales de columnas
        $dispositivos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_logs WHERE username = %s ORDER BY last_seen DESC, access_count DESC",
            $user->username
        ), ARRAY_A);
        
        if (empty($dispositivos)) {
            continue;
        }
        
        // Análisis de riesgo
        $nivel_riesgo = kmul_evaluar_riesgo_usuario_real($user, $dispositivos);
        
        switch ($nivel_riesgo) {
            case 'infractor':
                $infractores[$user->username] = $dispositivos;
                break;
            case 'sospechoso':
                $sospechosos[$user->username] = $dispositivos;
                break;
            case 'multiple':
                $sospechosos[$user->username] = $dispositivos;
                break;
            default:
                $normales[$user->username] = $dispositivos;
        }
    }
    
    return [
        'infractores' => $infractores,
        'sospechosos' => $sospechosos,
        'normales' => $normales
    ];
}

function kmul_evaluar_riesgo_usuario_real($stats, $dispositivos) {
    $tipos_dispositivos = [];
    $sistemas_operativos = [];
    
    foreach ($dispositivos as $d) {
        $tipos_dispositivos[] = $d['device_type'];
        $sistemas_operativos[] = $d['operating_system'];
    }
    
    $tipos_unicos = array_unique($tipos_dispositivos);
    $sistemas_unicos = array_unique($sistemas_operativos);
    
    // INFRACTOR: Diferentes tipos de dispositivos
    if (count($tipos_unicos) > 1) {
        // PC + Mobile = INFRACTOR
        if (in_array('PC', $tipos_unicos) && (in_array('Mobile', $tipos_unicos) || in_array('Tablet', $tipos_unicos))) {
            return 'infractor';
        }
        // Android + iOS = INFRACTOR
        if (in_array('Android', $sistemas_unicos) && in_array('iPhone', $sistemas_unicos)) {
            return 'infractor';
        }
    }
    
    // SOSPECHOSO: Muchas IPs diferentes del mismo tipo de dispositivo
    if ($stats->ips_diferentes >= 4 && count($tipos_unicos) == 1) {
        return 'sospechoso';
    }
    
    if ($stats->total_registros >= 3 && count($tipos_unicos) == 1) {
        return 'sospechoso';
    }
    
    if ($stats->ips_diferentes >= 2 && count($tipos_unicos) == 1 && count($sistemas_unicos) == 1) {
        return 'multiple';
    }
    
    return 'normal';
}

// Página para enviar advertencias
function kmul_send_warning_page() {
    $mensaje_resultado = '';
    $tipo_mensaje = '';

    if ($_POST && isset($_POST['enviar_advertencia'])) {
        $username = sanitize_text_field($_POST['username']);
        
        if (empty($username)) {
            $mensaje_resultado = 'Por favor ingresa un nombre de usuario.';
            $tipo_mensaje = 'error';
        } else {
            $user = get_user_by('login', $username);
            
            if (!$user) {
                $mensaje_resultado = "El usuario '{$username}' no existe.";
                $tipo_mensaje = 'error';
            } else {
                $resultado = kmul_enviar_advertencia_simple($user);
                
                if ($resultado) {
                    $mensaje_resultado = "✅ Advertencia enviada exitosamente a <strong>{$username}</strong> ({$user->user_email})";
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje_resultado = "❌ Error al enviar email a {$username}. Verifica la configuración de email.";
                    $tipo_mensaje = 'error';
                }
            }
        }
    }

    $recent_users = get_users(['number' => 20, 'orderby' => 'registered', 'order' => 'DESC']);
    ?>
    <div class="wrap">
        <h1>📧 Enviar Advertencia Manual</h1>
        
        <style>
            .kmul-warning-form { max-width: 600px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .kmul-warning-form input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
            .kmul-warning-form .button-primary { background: #007cba; border-color: #007cba; font-size: 16px; padding: 10px 20px; }
            .kmul-user-suggestions { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
            .kmul-user-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin-top: 10px; }
            .kmul-user-item { background: white; padding: 8px 12px; border-radius: 3px; cursor: pointer; border: 1px solid #ddd; font-size: 14px; }
            .kmul-user-item:hover { background: #e9ecef; }
        </style>
        
        <div style="margin: 20px 0;">
            <a href="<?php echo admin_url('admin.php?page=control_accesos_km'); ?>" class="button">⬅️ Volver al Control Principal</a>
        </div>
        
        <?php if ($mensaje_resultado): ?>
            <div class="notice notice-<?php echo $tipo_mensaje; ?>">
                <p><?php echo $mensaje_resultado; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="kmul-warning-form">
            <h3>Enviar Advertencia por Email</h3>
            <form method="POST" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="username">👤 Nombre de Usuario:</label></th>
                        <td>
                            <input type="text" id="username" name="username" placeholder="Ingresa el nombre de usuario (ej: MasterMX)" 
                                   value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>" required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="enviar_advertencia" class="button button-primary">📧 Enviar Advertencia</button>
                </p>
            </form>
        </div>
        
        <?php if (!empty($recent_users)): ?>
            <div class="kmul-user-suggestions">
                <h4>👥 Usuarios Recientes (Click para seleccionar):</h4>
                <div class="kmul-user-list">
                    <?php foreach ($recent_users as $recent_user): ?>
                        <div class="kmul-user-item" onclick="document.getElementById('username').value='<?php echo esc_js($recent_user->user_login); ?>'">
                            <?php echo esc_html($recent_user->user_login); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function kmul_enviar_advertencia_simple($user) {
    $to = $user->user_email;
    $subject = '⚠️ ADVERTENCIA - Múltiples dispositivos detectados en tu cuenta';
    
    $message = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #ffc107; text-align: center;">⚠️ ADVERTENCIA IMPORTANTE</h2>
        
        <p><strong>Estimado/a ' . esc_html($user->display_name) . ',</strong></p>
        
        <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <strong>⚠️ Hemos detectado que tu cuenta inició sesión en más de un dispositivo.</strong>
        </p>
        
        <p>Esto no está permitido según nuestros <strong>Términos y Condiciones de uso</strong>.</p>
        
        <div style="background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;">
            <p><strong>🚫 Te informamos que si continúas compartiendo tu acceso, tu cuenta será suspendida automáticamente.</strong></p>
        </div>
        
        <p>Si crees que esto es un error, contacta a nuestro equipo de soporte.</p>
        
        <hr style="margin: 30px 0;">
        
        <p style="font-size: 12px; color: #6c757d; text-align: center;">
            Este mensaje fue enviado automáticamente por el sistema de seguridad.<br>
            Fecha: ' . date('d/m/Y H:i:s') . '
        </p>
    </div>
    ';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Sistema de Seguridad <noreply@' . $_SERVER['HTTP_HOST'] . '>'
    );
    
    $enviado = wp_mail($to, $subject, $message, $headers);
    
    if ($enviado) {
        kmul_registrar_advertencia_simple($user->user_login, $user->user_email);
    }
    
    return $enviado;
}

function kmul_registrar_advertencia_simple($username, $email) {
    global $wpdb;
    
    $table_warnings = $wpdb->prefix . "kmul_warnings";
    $current_user = wp_get_current_user();
    
    $wpdb->insert($table_warnings, [
        'user_id' => get_user_by('login', $username)->ID,
        'username' => $username,
        'email' => $email,
        'warning_type' => 'multiple_devices',
        'sent_by' => $current_user->ID,
        'email_sent' => 1,
        'message_sent' => 0,
        'message_method' => '',
        'warning_level' => 1,
        'sent_at' => current_time('mysql')
    ]);
}

// Páginas de gestión
function kmul_blocked_users_page() {
    global $wpdb;
    
    if (isset($_POST['kmul_action']) && wp_verify_nonce($_POST['kmul_nonce'], 'kmul_action')) {
        kmul_procesar_accion();
    }
    
    $table_blocked = $wpdb->prefix . "kmul_blocked_users";
    $usuarios_bloqueados = $wpdb->get_results(
        "SELECT * FROM $table_blocked WHERE is_active = 1 ORDER BY blocked_at DESC"
    );
    
    ?>
    <div class="wrap">
        <h1>🚫 Usuarios Bloqueados</h1>
        
        <div style="margin: 20px 0;">
            <a href="<?php echo admin_url('admin.php?page=control_accesos_km'); ?>" class="button">⬅️ Volver al Control de Accesos</a>
        </div>
        
        <?php if (empty($usuarios_bloqueados)): ?>
            <div class="notice notice-success">
                <p>✅ No hay usuarios bloqueados actualmente.</p>
            </div>
        <?php else: ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>👤 Usuario</th>
                        <th>📅 Fecha de Bloqueo</th>
                        <th>📋 Motivo</th>
                        <th>⚙️ Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios_bloqueados as $bloqueado): ?>
                    <tr>
                        <td><strong><?php echo esc_html($bloqueado->username); ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($bloqueado->blocked_at)); ?></td>
                        <td><?php echo esc_html($bloqueado->reason); ?></td>
                        <td>
                            <button onclick="desbloquearUsuario('<?php echo esc_js($bloqueado->username); ?>')" 
                                    class="button" style="background: #28a745; color: white;">
                                🔓 Desbloquear
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    function desbloquearUsuario(username) {
        if (confirm('¿Desbloquear al usuario: ' + username + '?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="kmul_action" value="desbloquear">' +
                           '<input type="hidden" name="username" value="' + username + '">' +
                           '<input type="hidden" name="kmul_nonce" value="<?php echo wp_create_nonce('kmul_action'); ?>">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
    <?php
}

// Función para procesar las acciones AJAX
add_action('wp_ajax_warn_user', 'kmul_warn_user');
function kmul_warn_user() {
    // ✅ VERIFICACIÓN DE SEGURIDAD
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }
    
    // ✅ VERIFICAR NONCE (si se está usando)
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'kmul_admin_nonce')) {
        wp_die('Solicitud no válida.');
    }
    
    // ✅ OBTENER Y SANITIZAR EL USER_ID
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        wp_die('ID de usuario no válido.');
    }
    
    // ✅ OBTENER INFORMACIÓN DEL USUARIO
    $user = get_userdata($user_id);
    if (!$user) {
        wp_die('Usuario no encontrado.');
    }
    
    global $wpdb;
    $table_warnings = $wpdb->prefix . 'kmul_warnings';
    
    // ✅ VERIFICAR SI YA SE ENVIÓ ADVERTENCIA RECIENTE (últimas 24 horas)
    $recent_warning = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_warnings 
         WHERE user_id = %d 
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $user_id
    ));
    
    if ($recent_warning) {
        wp_die('Ya se envió una advertencia a este usuario en las últimas 24 horas.');
    }
    
    // ✅ PREPARAR CONTENIDO DEL EMAIL
    $user_email = $user->user_email;
    $user_name = $user->display_name ?: $user->user_login;
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // ✅ ASUNTO DEL EMAIL
    $subject = "[{$site_name}] Advertencia de Seguridad - Acceso desde Múltiples Dispositivos";
    
    // ✅ CUERPO DEL EMAIL (HTML)
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; border-radius: 5px; }
            .footer { padding: 15px; background: #343a40; color: white; text-align: center; font-size: 12px; }
            .button { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🚨 ADVERTENCIA DE SEGURIDAD</h1>
        </div>
        
        <div class='content'>
            <h2>Hola {$user_name},</h2>
            
            <div class='warning'>
                <h3>⚠️ Hemos detectado acceso sospechoso en tu cuenta</h3>
                <p><strong>Motivo:</strong> Acceso desde múltiples dispositivos (PC y móvil)</p>
                <p><strong>Fecha de detección:</strong> " . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <h3>¿Qué significa esto?</h3>
            <p>Nuestro sistema ha detectado que tu cuenta ha sido accedida desde diferentes tipos de dispositivos, lo que puede indicar:</p>
            <ul>
                <li>Compartir credenciales con otras personas</li>
                <li>Acceso no autorizado a tu cuenta</li>
                <li>Uso desde múltiples ubicaciones</li>
            </ul>
            
            <h3>¿Qué debes hacer?</h3>
            <ol>
                <li><strong>Cambiar tu contraseña inmediatamente</strong></li>
                <li>Asegurar que solo tú tienes acceso a tu cuenta</li>
                <li>Si no has sido tú, contacta al administrador</li>
            </ol>
            
            <p style='text-align: center;'>
                <a href='" . wp_login_url() . "?action=lostpassword' class='button'>Cambiar Contraseña</a>
            </p>
            
            <div class='warning'>
                <p><strong>IMPORTANTE:</strong> Si continúas con accesos sospechosos, tu cuenta podría ser temporalmente bloqueada por seguridad.</p>
            </div>
        </div>
        
        <div class='footer'>
            <p>Este mensaje fue enviado automáticamente por el sistema de seguridad de {$site_name}</p>
            <p>Si tienes preguntas, contacta: {$admin_email}</p>
        </div>
    </body>
    </html>
    ";
    
    // ✅ HEADERS PARA EMAIL HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Reply-To: ' . $admin_email
    );
    
    // ✅ ENVIAR EMAIL
    $email_sent = wp_mail($user_email, $subject, $message, $headers);
    
    if ($email_sent) {
        // ✅ REGISTRAR LA ADVERTENCIA EN LA BASE DE DATOS
        $wpdb->insert(
            $table_warnings,
            array(
                'user_id' => $user_id,
                'warning_type' => 'multiple_devices',
                'message' => 'Advertencia enviada por acceso desde múltiples dispositivos',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        // ✅ RESPUESTA EXITOSA
        wp_send_json_success('Advertencia enviada correctamente a ' . $user_email);
    } else {
        // ✅ ERROR EN ENVÍO
        wp_send_json_error('Error al enviar el email de advertencia.');
    }
}

// ✅ TAMBIÉN AGREGAR ESTA FUNCIÓN PARA DEPURACIÓN
add_action('wp_ajax_test_warning_system', 'kmul_test_warning_system');
function kmul_test_warning_system() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos.');
    }
    
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    
    $subject = "Prueba del Sistema de Advertencias - {$site_name}";
    $message = "
    <h2>✅ Prueba Exitosa</h2>
    <p>El sistema de advertencias está funcionando correctamente.</p>
    <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
    <p><strong>Plugin:</strong> KMultimedios Control de Accesos</p>
    ";
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $sent = wp_mail($admin_email, $subject, $message, $headers);
    
    if ($sent) {
        wp_send_json_success('Email de prueba enviado correctamente a ' . $admin_email);
    } else {
        wp_send_json_error('Error al enviar email de prueba.');
    }
}

// Funciones de soporte usando estructura real
function kmul_usuario_esta_bloqueado($username) {
    global $wpdb;
    $blocked = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}kmul_blocked_users WHERE username = %s AND is_active = 1",
        $username
    ));
    return $blocked > 0;
}

function kmul_contar_bloqueados() {
    global $wpdb;
    return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kmul_blocked_users WHERE is_active = 1");
}

function kmul_contar_advertencias() {
    global $wpdb;
    $table_warnings = $wpdb->prefix . "kmul_warnings";
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_warnings'");
    return $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_warnings") : 0;
}

function kmul_procesar_accion() {
    if (!isset($_POST['kmul_action']) || !current_user_can('manage_options')) return;
    
    global $wpdb;
    $action = sanitize_text_field($_POST['kmul_action']);
    $username = sanitize_text_field($_POST['username']);
    
    if ($action === 'bloquear') {
        $user = get_user_by('login', $username);
        if ($user) {
            $result = $wpdb->insert($wpdb->prefix . 'kmul_blocked_users', [
                'user_id' => $user->ID,
                'username' => $username,
                'reason' => 'Múltiples dispositivos detectados - Bloqueado por administrador',
                'blocked_by' => get_current_user_id(),
                'blocked_at' => current_time('mysql'),
                'is_active' => 1
            ]);
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>✅ Usuario ' . esc_html($username) . ' bloqueado exitosamente.</p></div>';
            }
        }
    }
    elseif ($action === 'desbloquear') {
        $current_user_id = get_current_user_id();
        $result = $wpdb->update(
            $wpdb->prefix . 'kmul_blocked_users',
            [
                'is_active' => 0,
                'unblocked_at' => current_time('mysql'),
                'unblocked_by' => $current_user_id
            ],
            ['username' => $username, 'is_active' => 1]
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>✅ Usuario ' . esc_html($username) . ' desbloqueado exitosamente.</p></div>';
        }
    }
    elseif ($action === 'advertir') {
        $user = get_user_by('login', $username);
        if ($user) {
            $resultado = kmul_enviar_advertencia_simple($user);
            
            if ($resultado) {
                echo '<div class="notice notice-success"><p>✅ Advertencia enviada exitosamente a ' . esc_html($username) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Error al enviar advertencia a ' . esc_html($username) . '</p></div>';
            }
        }
    }
}

// Verificar y bloquear acceso
add_action('init', function() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (kmul_usuario_esta_bloqueado($current_user->user_login)) {
            wp_logout();
            wp_redirect(wp_login_url() . '?blocked=1');
            exit;
        }
    }
});

add_action('login_message', function($message) {
    if (isset($_GET['blocked'])) {
        $message .= '<div class="notice notice-error"><p><strong>Tu cuenta ha sido suspendida. Contacta al administrador.</strong></p></div>';
    }
    return $message;
});

// Sistema de captura de logins
add_action('wp_login', 'kmul_capturar_login', 10, 2);

function kmul_capturar_login($user_login, $user) {
    global $wpdb;
    
    if (!$user || !$user_login) {
        error_log('KMUL ERROR: Usuario o user_login vacío');
        return;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    // Detectar información del dispositivo
    $operating_system = kmul_detectar_so($user_agent);
    $browser = kmul_detectar_navegador($user_agent);
    $device_type = kmul_detectar_dispositivo($user_agent);

    $table_logs = $wpdb->prefix . "kmul_device_logs";

    // Buscar registro existente
    $existing_log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_logs WHERE username = %s AND ip_address = %s AND operating_system = %s AND browser = %s AND device_type = %s",
        $user_login, $ip_address, $operating_system, $browser, $device_type
    ));

    if ($existing_log) {
        $result = $wpdb->update($table_logs, [
            'last_seen' => current_time('mysql'),
            'login_date' => current_time('mysql'),
            'access_count' => $existing_log->access_count + 1,
            'updated_at' => current_time('mysql')
        ], ['id' => $existing_log->id]);
    } else {
        $result = $wpdb->insert($table_logs, [
            'user_id' => $user->ID,
            'username' => $user_login,
            'login_date' => current_time('mysql'),
            'ip_address' => $ip_address,
            'operating_system' => $operating_system,
            'browser' => $browser,
            'device_type' => $device_type,
            'user_agent' => substr($user_agent, 0, 500),
            'access_count' => 1,
            'last_seen' => current_time('mysql'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
    }
}

// Funciones de detección de dispositivos
function kmul_detectar_so($user_agent) {
    $os = 'Unknown';
    
    if (preg_match('/windows nt 10/i', $user_agent)) {
        $os = 'Windows 10';
    } elseif (preg_match('/windows nt 6.3/i', $user_agent)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/windows nt 6.2/i', $user_agent)) {
        $os = 'Windows 8';
    } elseif (preg_match('/windows nt 6.1/i', $user_agent)) {
        $os = 'Windows 7';
    } elseif (preg_match('/windows nt/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
        $os = 'Mac OS X';
    } elseif (preg_match('/linux/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ios/i', $user_agent)) {
        $os = 'iPhone';
    } elseif (preg_match('/ipad/i', $user_agent)) {
        $os = 'iPad';
    }
    
    return $os;
}

function kmul_detectar_navegador($user_agent) {
    $browser = 'Unknown';
    
    if (preg_match('/firefox/i', $user_agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/chrome/i', $user_agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $user_agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/edge/i', $user_agent)) {
        $browser = 'Edge';
    } elseif (preg_match('/opera|opr/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/msie|trident/i', $user_agent)) {
        $browser = 'Internet Explorer';
    }
    
    return $browser;
}

function kmul_detectar_dispositivo($user_agent) {
    if (preg_match('/mobile|android|iphone|ipod|blackberry|webos/i', $user_agent)) {
        return 'Mobile';
    } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
        return 'Tablet';
    } else {
        return 'PC';
    }
}

// Página de advertencias enviadas
function kmul_warnings_page() {
    global $wpdb;
    
    $table_warnings = $wpdb->prefix . "kmul_warnings";
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_warnings'");
    
    if (!$table_exists) {
        // Crear tabla si no existe
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_warnings (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            username varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            warning_type varchar(50) NOT NULL,
            sent_by int(11) NOT NULL,
            email_sent tinyint(1) DEFAULT 0,
            message_sent tinyint(1) DEFAULT 0,
            message_method varchar(50) DEFAULT '',
            warning_level int(1) DEFAULT 1,
            sent_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    $advertencias = $wpdb->get_results(
        "SELECT * FROM $table_warnings ORDER BY sent_at DESC LIMIT 100"
    );
    ?>
    <div class="wrap">
        <h1>📧 Historial de Advertencias Enviadas</h1>
        
        <div style="margin: 20px 0;">
            <a href="<?php echo admin_url('admin.php?page=control_accesos_km'); ?>" class="button">⬅️ Volver al Control de Accesos</a>
            <a href="<?php echo admin_url('admin.php?page=kmul_send_warning'); ?>" class="button button-primary">📧 Enviar Nueva Advertencia</a>
        </div>
        
        <?php if (empty($advertencias)): ?>
            <div class="notice notice-info">
                <p>📋 No se han enviado advertencias aún.</p>
            </div>
        <?php else: ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>👤 Usuario</th>
                        <th>📧 Email</th>
                        <th>⚠️ Tipo</th>
                        <th>📅 Fecha Enviada</th>
                        <th>👨‍💼 Enviada Por</th>
                        <th>📊 Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advertencias as $advertencia): ?>
                    <?php
                    $sent_by_user = get_userdata($advertencia->sent_by);
                    $sent_by_name = $sent_by_user ? $sent_by_user->display_name : 'Sistema';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($advertencia->username); ?></strong></td>
                        <td><?php echo esc_html($advertencia->email); ?></td>
                        <td>
                            <?php if ($advertencia->warning_type === 'multiple_devices'): ?>
                                📱 Múltiples Dispositivos
                            <?php else: ?>
                                ⚠️ <?php echo esc_html($advertencia->warning_type); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($advertencia->sent_at)); ?></td>
                        <td><?php echo esc_html($sent_by_name); ?></td>
                        <td>
                            <?php if ($advertencia->email_sent): ?>
                                <span style="color: #28a745;">✅ Email Enviado</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">❌ Error en Envío</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}