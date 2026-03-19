<?php
/**
 * Panel de administración: gestión de dispositivos PWA
 */

defined('ABSPATH') || exit;

class PWA_Admin {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_pwa_revoke_device', [self::class, 'handle_revoke']);
        add_action('admin_notices', [self::class, 'show_notices']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'PWA VIP Dispositivos',
            'PWA VIP',
            'manage_options',
            'pwa-vip-devices',
            [self::class, 'render_page'],
            'dashicons-smartphone',
            80
        );
        add_submenu_page(
            'pwa-vip-devices',
            'Registro de Accesos',
            'Registro de Accesos',
            'manage_options',
            'pwa-vip-logs',
            [self::class, 'render_logs']
        );
    }

    public static function render_page(): void {
        $devices = PWA_Database::get_all_devices(100);
        $total   = PWA_Database::count_active_devices();
        ?>
        <div class="wrap pwa-admin">
            <h1>
                <span class="dashicons dashicons-smartphone"></span>
                PWA VIP &mdash; Dispositivos Registrados
            </h1>
            <p class="description">
                Total de dispositivos activos: <strong><?php echo $total; ?></strong>
            </p>

            <?php if (empty($devices)): ?>
                <div class="notice notice-info"><p>No hay dispositivos registrados aún.</p></div>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Dispositivo</th>
                        <th>Registrado</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $d): ?>
                    <tr>
                        <td><strong><?php echo esc_html($d->display_name); ?></strong></td>
                        <td><?php echo esc_html($d->user_email); ?></td>
                        <td><?php echo esc_html($d->device_name ?: 'Desconocido'); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($d->registered_date))); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($d->last_access))); ?></td>
                        <td>
                            <?php if ($d->is_active): ?>
                                <span class="pwa-badge pwa-badge--active">Activo</span>
                            <?php else: ?>
                                <span class="pwa-badge pwa-badge--inactive">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d->is_active): ?>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                                  onsubmit="return confirm('¿Revocar dispositivo de <?php echo esc_js($d->display_name); ?>?');">
                                <?php wp_nonce_field('pwa_revoke_' . $d->user_id); ?>
                                <input type="hidden" name="action"  value="pwa_revoke_device">
                                <input type="hidden" name="user_id" value="<?php echo (int) $d->user_id; ?>">
                                <button type="submit" class="button button-small button-link-delete">
                                    Revocar
                                </button>
                            </form>
                            <?php else: ?>
                                <em>—</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <hr>
            <h2>Información del Shortcode</h2>
            <p>Usa <code>[pwa_download_link]</code> en cualquier página para mostrar el botón de instalación a usuarios VIP.</p>
            <p>Atributo opcional: <code>[pwa_download_link texto="Descargar App"]</code></p>

            <h2>Niveles VIP Configurados</h2>
            <p>IDs de membresía PMP: <code><?php echo implode(', ', PWA_PMP::VIP_LEVELS); ?></code></p>
        </div>

        <style>
        .pwa-admin .pwa-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
        .pwa-admin .pwa-badge--active { background:#d4edda; color:#155724; }
        .pwa-admin .pwa-badge--inactive { background:#f8d7da; color:#721c24; }
        </style>
        <?php
    }

    public static function render_logs(): void {
        $logs = array_reverse(get_option('pwa_vip_access_log', []));
        ?>
        <div class="wrap">
            <h1>PWA VIP &mdash; Registro de Accesos</h1>
            <?php if (empty($logs)): ?>
                <div class="notice notice-info"><p>No hay registros de acceso aún.</p></div>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Evento</th>
                        <th>Usuario ID</th>
                        <th>IP</th>
                        <th>Datos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($logs, 0, 200) as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['time']); ?></td>
                        <td><code><?php echo esc_html($log['event']); ?></code></td>
                        <td><?php echo (int) $log['user_id']; ?></td>
                        <td><?php echo esc_html($log['ip']); ?></td>
                        <td><?php echo esc_html(json_encode($log['data'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_revoke(): void {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id || !check_admin_referer('pwa_revoke_' . $user_id)) {
            wp_die('Acción no permitida.');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos.');
        }

        PWA_Database::revoke_device($user_id);
        wp_redirect(admin_url('admin.php?page=pwa-vip-devices&pwa_revoked=1'));
        exit;
    }

    public static function show_notices(): void {
        if (isset($_GET['pwa_revoked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Dispositivo revocado correctamente.</p></div>';
        }
    }
}
