<?php
/**
 * Interfaz de administración moderna y responsiva
 * 
 * @since 5.0.0
 */
class KMUL_Admin {
    
    private $database;
    private $core;
    
    public function __construct(KMUL_Database $database, KMUL_Core $core) {
        $this->database = $database;
        $this->core = $core;
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
    }
    
    /**
     * Agregar menús de administración
     */
    public function add_admin_menus() {
        // Menú principal
        add_menu_page(
            'Control de Accesos',
            'Control de Accesos',
            'manage_options',
            'kmul-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-shield-alt',
            90
        );
        
        // Submenús
        add_submenu_page(
            'kmul-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'kmul-dashboard',
            [$this, 'render_dashboard_page']
        );
        
        add_submenu_page(
            'kmul-dashboard',
            'Análisis de Usuarios',
            'Análisis de Usuarios',
            'manage_options',
            'kmul-users-analysis',
            [$this, 'render_users_analysis_page']
        );
        
        add_submenu_page(
            'kmul-dashboard',
            'Usuarios Bloqueados',
            'Usuarios Bloqueados',
            'manage_options',
            'kmul-blocked-users',
            [$this, 'render_blocked_users_page']
        );
        
        add_submenu_page(
            'kmul-dashboard',
            'Advertencias Enviadas',
            'Advertencias Enviadas',
            'manage_options',
            'kmul-warnings',
            [$this, 'render_warnings_page']
        );
        
        add_submenu_page(
            'kmul-dashboard',
            'Configuración',
            'Configuración',
            'manage_options',
            'kmul-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'kmul-dashboard',
            'Herramientas',
            'Herramientas',
            'manage_options',
            'kmul-tools',
            [$this, 'render_tools_page']
        );
    }
    
    /**
     * Cargar assets de administración
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'kmul') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('kmul-admin', KMUL_PLUGIN_URL . 'assets/admin.js', ['jquery'], KMUL_PLUGIN_VERSION, true);
        wp_enqueue_style('kmul-admin', KMUL_PLUGIN_URL . 'assets/admin.css', [], KMUL_PLUGIN_VERSION);
        
        wp_localize_script('kmul-admin', 'kmulAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kmul_ajax_action'),
            'strings' => [
                'confirm_block' => '¿Estás seguro de bloquear este usuario?',
                'confirm_unblock' => '¿Estás seguro de desbloquear este usuario?',
                'confirm_warning' => '¿Enviar advertencia a este usuario?',
                'loading' => 'Procesando...',
                'error' => 'Error en la operación'
            ]
        ]);
    }
    
    /**
     * Manejar acciones de administración
     */
    public function handle_admin_actions() {
        if (!isset($_POST['kmul_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'kmul_admin_action')) {
            wp_die('Token de seguridad inválido');
        }
        
        $action = sanitize_text_field($_POST['kmul_action']);
        $redirect_url = admin_url('admin.php?page=' . ($_POST['page'] ?? 'kmul-dashboard'));
        
        switch ($action) {
            case 'update_settings':
                $this->handle_update_settings();
                break;
                
            case 'export_data':
                $this->handle_export_data();
                return; // No redirect for exports
                
            case 'cleanup_data':
                $this->handle_cleanup_data();
                break;
                
            case 'repair_database':
                $this->handle_repair_database();
                break;
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Renderizar página de dashboard
     */
    public function render_dashboard_page() {
        $stats = $this->core->get_dashboard_statistics();
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Control de Accesos - Dashboard</h1>
            
            <div class="kmul-stats-grid">
                <div class="kmul-stat-card critical">
                    <div class="stat-number"><?php echo $stats['critical_users']; ?></div>
                    <div class="stat-label">Usuarios Críticos</div>
                </div>
                
                <div class="kmul-stat-card warning">
                    <div class="stat-number"><?php echo $stats['high_risk_users']; ?></div>
                    <div class="stat-label">Alto Riesgo</div>
                </div>
                
                <div class="kmul-stat-card blocked">
                    <div class="stat-number"><?php echo $stats['blocked_users']; ?></div>
                    <div class="stat-label">Bloqueados</div>
                </div>
                
                <div class="kmul-stat-card info">
                    <div class="stat-number"><?php echo $stats['recent_warnings']; ?></div>
                    <div class="stat-label">Advertencias (30 días)</div>
                </div>
                
                <div class="kmul-stat-card success">
                    <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                    <div class="stat-label">Usuarios Activos</div>
                </div>
            </div>
            
            <div class="kmul-dashboard-actions">
                <a href="<?php echo admin_url('admin.php?page=kmul-users-analysis'); ?>" class="button button-primary">
                    Ver Análisis Completo
                </a>
                <a href="<?php echo admin_url('admin.php?page=kmul-blocked-users'); ?>" class="button">
                    Gestionar Bloqueados
                </a>
                <a href="<?php echo admin_url('admin.php?page=kmul-settings'); ?>" class="button">
                    Configuración
                </a>
            </div>
            
            <?php if ($stats['critical_users'] > 0): ?>
            <div class="notice notice-error">
                <p><strong>Atención:</strong> Hay <?php echo $stats['critical_users']; ?> usuario(s) con nivel de riesgo crítico que requieren acción inmediata.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de análisis de usuarios
     */
    public function render_users_analysis_page() {
        $page = absint($_GET['paged'] ?? 1);
        $analysis = $this->core->get_users_analysis($page, 25);
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Análisis de Usuarios</h1>
            
            <div class="kmul-filters">
                <button class="button filter-btn active" data-filter="all">Todos</button>
                <button class="button filter-btn critical" data-filter="critical">Críticos</button>
                <button class="button filter-btn warning" data-filter="high">Alto Riesgo</button>
                <button class="button filter-btn" data-filter="medium">Medio Riesgo</button>
                <button class="button filter-btn" data-filter="low">Bajo Riesgo</button>
            </div>
            
            <?php foreach (['critical', 'high', 'medium', 'low'] as $level): ?>
                <?php if (!empty($analysis['classification'][$level])): ?>
                <div class="kmul-risk-section" data-risk-level="<?php echo $level; ?>">
                    <h2 class="section-header <?php echo $level; ?>">
                        <?php echo $this->get_risk_level_title($level); ?> 
                        (<?php echo count($analysis['classification'][$level]); ?>)
                    </h2>
                    
                    <?php foreach ($analysis['classification'][$level] as $user_data): ?>
                        <?php $this->render_user_analysis_card($user_data); ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar tarjeta de análisis de usuario
     */
    private function render_user_analysis_card($user_data) {
        $user_stat = $user_data['user_data'];
        $risk = $user_data['risk_analysis'];
        $wp_user = $user_data['wp_user'];
        $is_blocked = $user_data['is_blocked'];
        ?>
        <div class="kmul-user-card <?php echo $risk['risk_level']; ?> <?php echo $is_blocked ? 'blocked' : ''; ?>">
            <div class="user-header">
                <div class="user-info">
                    <h4><?php echo esc_html($user_stat->username); ?></h4>
                    <?php if ($wp_user): ?>
                        <p><?php echo esc_html($wp_user->user_email); ?></p>
                    <?php endif; ?>
                </div>
                <div class="risk-badge <?php echo $risk['risk_level']; ?>">
                    <?php echo strtoupper($risk['risk_level']); ?>
                </div>
            </div>
            
            <div class="user-stats">
                <div class="stat-item">
                    <span class="label">Dispositivos:</span>
                    <span class="value"><?php echo $user_stat->unique_devices; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">IPs:</span>
                    <span class="value"><?php echo $user_stat->unique_ips; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Accesos:</span>
                    <span class="value"><?php echo $user_stat->total_accesses; ?></span>
                </div>
                <div class="stat-item">
                    <span class="label">Score:</span>
                    <span class="value"><?php echo $risk['risk_score']; ?></span>
                </div>
            </div>
            
            <?php if (!empty($risk['risk_factors'])): ?>
            <div class="risk-factors">
                <strong>Factores de riesgo:</strong>
                <ul>
                    <?php foreach ($risk['risk_factors'] as $factor): ?>
                        <li><?php echo esc_html($factor); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="user-actions">
                <?php if ($is_blocked): ?>
                    <button class="button unblock-btn" data-user-id="<?php echo $user_stat->user_id; ?>">
                        Desbloquear
                    </button>
                    <span class="status blocked">BLOQUEADO</span>
                <?php else: ?>
                    <button class="button button-primary warn-btn" data-user-id="<?php echo $user_stat->user_id; ?>">
                        Advertir
                    </button>
                    <button class="button block-btn" data-user-id="<?php echo $user_stat->user_id; ?>">
                        Bloquear
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de usuarios bloqueados
     */
    public function render_blocked_users_page() {
        $page = absint($_GET['paged'] ?? 1);
        $blocked_users = $this->database->get_blocked_users($page, 20);
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Usuarios Bloqueados</h1>
            
            <?php if (empty($blocked_users)): ?>
                <div class="notice notice-success">
                    <p>No hay usuarios bloqueados actualmente.</p>
                </div>
            <?php else: ?>
                <div class="kmul-table-wrapper">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Motivo</th>
                                <th>Fecha de Bloqueo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked_users as $blocked): ?>
                            <tr>
                                <td><strong><?php echo esc_html($blocked->username); ?></strong></td>
                                <td><?php echo esc_html($blocked->user_email ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($blocked->reason); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($blocked->blocked_at)); ?></td>
                                <td>
                                    <button class="button unblock-btn" data-user-id="<?php echo $blocked->user_id; ?>">
                                        Desbloquear
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de advertencias
     */
    public function render_warnings_page() {
        $page = absint($_GET['paged'] ?? 1);
        $warnings = $this->database->get_warnings($page, 30);
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Advertencias Enviadas</h1>
            
            <?php if (empty($warnings)): ?>
                <div class="notice notice-info">
                    <p>No se han enviado advertencias aún.</p>
                </div>
            <?php else: ?>
                <div class="kmul-table-wrapper">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Métodos</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warnings as $warning): ?>
                            <tr>
                                <td><strong><?php echo esc_html($warning->username); ?></strong></td>
                                <td><?php echo esc_html($warning->email); ?></td>
                                <td><?php echo esc_html($warning->warning_type); ?></td>
                                <td>
                                    <?php if ($warning->email_sent): ?>
                                        <span class="method-badge email">Email</span>
                                    <?php endif; ?>
                                    <?php if ($warning->message_sent): ?>
                                        <span class="method-badge message"><?php echo esc_html($warning->message_method); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($warning->sent_at)); ?></td>
                                <td>
                                    <?php if ($this->database->is_user_blocked($warning->user_id)): ?>
                                        <span class="status-badge blocked">Después bloqueado</span>
                                    <?php else: ?>
                                        <span class="status-badge warning">Advertido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Configuración</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('kmul_admin_action'); ?>
                <input type="hidden" name="kmul_action" value="update_settings">
                <input type="hidden" name="page" value="kmul-settings">
                
                <div class="kmul-settings-sections">
                    <div class="settings-section">
                        <h2>Límites y Umbrales</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Máximo dispositivos por usuario</th>
                                <td>
                                    <input type="number" name="max_devices_per_user" 
                                           value="<?php echo $this->database->get_setting('max_devices_per_user', 2); ?>" 
                                           min="1" max="10">
                                    <p class="description">Número máximo de tipos de dispositivos permitidos</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Máximo IPs por dispositivo</th>
                                <td>
                                    <input type="number" name="max_ips_per_device" 
                                           value="<?php echo $this->database->get_setting('max_ips_per_device', 3); ?>" 
                                           min="1" max="20">
                                    <p class="description">IPs diferentes permitidas para el mismo dispositivo</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Umbral para advertencias</th>
                                <td>
                                    <input type="number" name="warning_threshold" 
                                           value="<?php echo $this->database->get_setting('warning_threshold', 2); ?>" 
                                           min="1" max="10">
                                    <p class="description">Score de riesgo que activa advertencias automáticas</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="settings-section">
                        <h2>Acciones Automáticas</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Advertencias automáticas</th>
                                <td>
                                    <input type="checkbox" name="auto_warning_enabled" value="1" 
                                           <?php checked($this->database->get_setting('auto_warning_enabled', false)); ?>>
                                    <label>Enviar advertencias automáticamente</label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Bloqueo automático</th>
                                <td>
                                    <input type="checkbox" name="auto_block_enabled" value="1" 
                                           <?php checked($this->database->get_setting('auto_block_enabled', false)); ?>>
                                    <label>Bloquear automáticamente usuarios críticos</label>
                                    <p class="description">¡Usar con precaución!</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="settings-section">
                        <h2>Métodos de Notificación</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Advertencias por email</th>
                                <td>
                                    <input type="checkbox" name="email_warnings" value="1" 
                                           <?php checked($this->database->get_setting('email_warnings', true)); ?>>
                                    <label>Enviar advertencias por email</label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Mensajes internos</th>
                                <td>
                                    <input type="checkbox" name="internal_messages" value="1" 
                                           <?php checked($this->database->get_setting('internal_messages', true)); ?>>
                                    <label>Enviar mensajes al sistema interno</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="settings-section">
                        <h2>Mantenimiento</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Días para limpieza</th>
                                <td>
                                    <input type="number" name="cleanup_days" 
                                           value="<?php echo $this->database->get_setting('cleanup_days', 90); ?>" 
                                           min="30" max="365">
                                    <p class="description">Días después de los cuales eliminar registros antiguos</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button('Guardar Configuración'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de herramientas
     */
    public function render_tools_page() {
        $integrity = $this->core->verify_data_integrity();
        ?>
        <div class="wrap kmul-admin-page">
            <h1>Herramientas y Mantenimiento</h1>
            
            <div class="kmul-tools-grid">
                <div class="tool-section">
                    <h2>Exportar Datos</h2>
                    <p>Exportar análisis de usuarios para revisión externa</p>
                    
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('kmul_admin_action'); ?>
                        <input type="hidden" name="kmul_action" value="export_data">
                        <input type="hidden" name="format" value="csv">
                        <button type="submit" class="button">Exportar CSV</button>
                    </form>
                    
                    <form method="post" action="" style="display: inline; margin-left: 10px;">
                        <?php wp_nonce_field('kmul_admin_action'); ?>
                        <input type="hidden" name="kmul_action" value="export_data">
                        <input type="hidden" name="format" value="json">
                        <button type="submit" class="button">Exportar JSON</button>
                    </form>
                </div>
                
                <div class="tool-section">
                    <h2>Limpieza de Datos</h2>
                    <p>Eliminar registros antiguos para optimizar rendimiento</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('kmul_admin_action'); ?>
                        <input type="hidden" name="kmul_action" value="cleanup_data">
                        <input type="hidden" name="page" value="kmul-tools">
                        <button type="submit" class="button button-secondary">Limpiar Datos Antiguos</button>
                    </form>
                </div>
                
                <div class="tool-section">
                    <h2>Integridad de Datos</h2>
                    <p>Estado: <span class="status-<?php echo $integrity['status']; ?>"><?php echo $integrity['status']; ?></span></p>
                    
                    <?php if (!empty($integrity['issues'])): ?>
                        <ul class="issues-list">
                            <?php foreach ($integrity['issues'] as $issue): ?>
                                <li><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field('kmul_admin_action'); ?>
                            <input type="hidden" name="kmul_action" value="repair_database">
                            <input type="hidden" name="page" value="kmul-tools">
                            <button type="submit" class="button button-primary">Reparar Problemas</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="tool-section">
                    <h2>Información del Sistema</h2>
                    <ul>
                        <li><strong>Versión del Plugin:</strong> <?php echo KMUL_PLUGIN_VERSION; ?></li>
                        <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
                        <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                        <li><strong>Métodos de mensajería disponibles:</strong>
                            <?php
                            $methods = [];
                            if (function_exists('um_user_send_message')) $methods[] = 'Ultimate Member';
                            if (function_exists('messages_new_message')) $methods[] = 'BuddyPress';
                            if (function_exists('wpuf_send_message')) $methods[] = 'WPUF';
                            echo implode(', ', $methods) ?: 'Solo sistema personalizado';
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Manejar actualización de configuración
     */
    private function handle_update_settings() {
        $settings = [
            'max_devices_per_user' => ['value' => absint($_POST['max_devices_per_user'] ?? 2), 'type' => 'int'],
            'max_ips_per_device' => ['value' => absint($_POST['max_ips_per_device'] ?? 3), 'type' => 'int'],
            'warning_threshold' => ['value' => absint($_POST['warning_threshold'] ?? 2), 'type' => 'int'],
            'auto_warning_enabled' => ['value' => !empty($_POST['auto_warning_enabled']), 'type' => 'boolean'],
            'auto_block_enabled' => ['value' => !empty($_POST['auto_block_enabled']), 'type' => 'boolean'],
            'email_warnings' => ['value' => !empty($_POST['email_warnings']), 'type' => 'boolean'],
            'internal_messages' => ['value' => !empty($_POST['internal_messages']), 'type' => 'boolean'],
            'cleanup_days' => ['value' => absint($_POST['cleanup_days'] ?? 90), 'type' => 'int']
        ];
        
        foreach ($settings as $key => $config) {
            $this->database->update_setting($key, $config['value'], $config['type']);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Configuración guardada correctamente.</p></div>';
        });
    }
    
    /**
     * Manejar exportación de datos
     */
    private function handle_export_data() {
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $this->core->export_analysis_data($format);
    }
    
    /**
     * Manejar limpieza de datos
     */
    private function handle_cleanup_data() {
        $deleted = $this->database->cleanup_old_data();
        
        add_action('admin_notices', function() use ($deleted) {
            echo '<div class="notice notice-success"><p>Se eliminaron ' . $deleted . ' registros antiguos.</p></div>';
        });
    }
    
    /**
     * Manejar reparación de base de datos
     */
    private function handle_repair_database() {
        $result = $this->core->repair_data_integrity();
        
        add_action('admin_notices', function() use ($result) {
            if (!empty($result['repairs'])) {
                echo '<div class="notice notice-success"><p>Reparaciones completadas: ' . implode(', ', $result['repairs']) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>No se requirieron reparaciones.</p></div>';
            }
        });
    }
    
    /**
     * Obtener título para nivel de riesgo
     */
    private function get_risk_level_title($level) {
        $titles = [
            'critical' => 'USUARIOS CRÍTICOS - ACCIÓN INMEDIATA',
            'high' => 'ALTO RIESGO - REVISAR',
            'medium' => 'RIESGO MEDIO - MONITOREAR',
            'low' => 'BAJO RIESGO - NORMALES'
        ];
        
        return $titles[$level] ?? 'DESCONOCIDO';
    }
}
?>