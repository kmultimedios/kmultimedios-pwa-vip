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
        // Acción: limpiar log
        if (isset($_POST['pwa_clear_audit']) && check_admin_referer('pwa_clear_audit')) {
            PWA_Database::clear_audit_log();
            echo '<div class="notice notice-success is-dismissible"><p>Registro de auditoría limpiado.</p></div>';
        }

        $page    = max(1, (int) ($_GET['plog'] ?? 1));
        $limit   = 100;
        $offset  = ($page - 1) * $limit;
        $logs    = PWA_Database::get_audit_log($limit, $offset);
        $total   = PWA_Database::count_audit_log();
        $pages   = (int) ceil($total / $limit);

        $action_labels = [
            'streams_access'      => ['Vio cámaras',        '#1a6e1a', '#d4edda'],
            'device_verified'     => ['Autenticó biometría','#0d4f8a', '#d0e8ff'],
            'device_registered'   => ['Registró dispositivo','#5a3b8a','#ede0ff'],
            'device_deleted'      => ['Eliminó dispositivo', '#8a5a00', '#fff3cd'],
            'device_revoked_admin'=> ['Admin revocó',       '#721c24', '#f8d7da'],
            'verify_failed'       => ['Fallo biometría',    '#721c24', '#f8d7da'],
        ];
        ?>
        <div class="wrap">
            <h1>PWA VIP &mdash; Auditoría de Accesos</h1>
            <p class="description">
                Total de registros: <strong><?php echo number_format($total); ?></strong>
                &nbsp;|&nbsp;
                Página <?php echo $page; ?> de <?php echo max(1, $pages); ?>
            </p>

            <form method="post" style="margin-bottom:12px;"
                  onsubmit="return confirm('¿Limpiar todo el registro de auditoría?');">
                <?php wp_nonce_field('pwa_clear_audit'); ?>
                <input type="hidden" name="pwa_clear_audit" value="1">
                <button type="submit" class="button button-link-delete">Limpiar registro</button>
            </form>

            <?php if (empty($logs)): ?>
                <div class="notice notice-info"><p>No hay registros de auditoría aún.</p></div>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:145px">Fecha/Hora</th>
                        <th style="width:160px">Acción</th>
                        <th>Usuario</th>
                        <th style="width:125px">IP</th>
                        <th style="width:80px">Dispositivo</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $label = $action_labels[$log->action] ?? [$log->action, '#444', '#eee'];
                        $details = $log->details ? json_decode($log->details, true) : [];
                        $details_str = '';
                        if ($details) {
                            $parts = [];
                            foreach ($details as $k => $v) $parts[] = "<b>{$k}</b>: " . esc_html($v);
                            $details_str = implode(' &nbsp;·&nbsp; ', $parts);
                        }
                    ?>
                    <tr>
                        <td style="font-size:12px"><?php echo esc_html($log->created_at); ?></td>
                        <td>
                            <span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;
                                         color:<?php echo $label[1]; ?>;background:<?php echo $label[2]; ?>">
                                <?php echo esc_html($label[0]); ?>
                            </span>
                        </td>
                        <td style="font-size:12px"><?php echo esc_html($log->user_email ?: '—'); ?></td>
                        <td style="font-size:12px;font-family:monospace"><?php echo esc_html($log->ip); ?></td>
                        <td style="font-size:11px"><?php echo esc_html($log->device_type ?: '—'); ?></td>
                        <td style="font-size:11px"><?php echo $details_str ?: '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
            <div class="tablenav bottom" style="margin-top:8px">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?page=pwa-vip-logs&plog=<?php echo $i; ?>"
                       class="button<?php echo $i === $page ? ' button-primary' : ''; ?>"
                       style="margin:2px"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
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

        PWA_Database::admin_revoke_all_devices($user_id);
        wp_redirect(admin_url('admin.php?page=pwa-vip-devices&pwa_revoked=1'));
        exit;
    }

    public static function show_notices(): void {
        if (isset($_GET['pwa_revoked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Dispositivo revocado correctamente.</p></div>';
        }
    }
}
