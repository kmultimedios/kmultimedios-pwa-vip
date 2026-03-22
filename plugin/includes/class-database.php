<?php
/**
 * Gestión de la base de datos
 * v2.0 – 2 ranuras por usuario (mobile + desktop) con tracking de reemplazos anuales
 */

defined('ABSPATH') || exit;

class PWA_Database {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'pwa_user_devices';
    }

    public static function replacements_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pwa_device_replacements';
    }

    public static function audit_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pwa_audit_log';
    }

    // ── Instalación / Migración ─────────────────────────────────────────────
    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Tabla principal de dispositivos
        $devices_table = self::table_name();
        $sql_devices = "CREATE TABLE {$devices_table} (
            id              MEDIUMINT(9)            NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20)              NOT NULL,
            device_type     ENUM('mobile','desktop') NOT NULL DEFAULT 'mobile',
            credential_id   VARCHAR(512)            NOT NULL COMMENT 'WebAuthn credential ID (base64url)',
            public_key      TEXT                    NOT NULL COMMENT 'COSE public key (base64url)',
            device_name     VARCHAR(255)            DEFAULT '',
            sign_count      BIGINT(20)              DEFAULT 0,
            registered_date DATETIME                DEFAULT CURRENT_TIMESTAMP,
            last_access     DATETIME                DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active       TINYINT(1)              DEFAULT 1,
            deleted_date    DATETIME                DEFAULT NULL,
            deleted_by_user TINYINT(1)              DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_cred_id (credential_id(255))
        ) {$charset};";

        // Tabla de tracking de reemplazos anuales
        $replacements_table = self::replacements_table();
        $sql_replacements = "CREATE TABLE {$replacements_table} (
            id          MEDIUMINT(9)            NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20)              NOT NULL,
            device_type ENUM('mobile','desktop') NOT NULL,
            year        SMALLINT(4)             NOT NULL,
            count       TINYINT(1)              DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_slot_year (user_id, device_type, year)
        ) {$charset};";

        // Tabla de auditoría de accesos
        $audit_table = self::audit_table();
        $sql_audit = "CREATE TABLE {$audit_table} (
            id          BIGINT(20)   NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20)   NOT NULL DEFAULT 0,
            user_email  VARCHAR(100) NOT NULL DEFAULT '',
            ip          VARCHAR(45)  NOT NULL DEFAULT '',
            action      VARCHAR(50)  NOT NULL,
            device_type VARCHAR(20)  DEFAULT NULL,
            details     TEXT         DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id   (user_id),
            KEY idx_created_at (created_at),
            KEY idx_action    (action)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_devices);
        dbDelta($sql_replacements);
        dbDelta($sql_audit);

        update_option('pwa_vip_db_version', PWA_VIP_VERSION);
    }

    // ── Detección de tipo de dispositivo ────────────────────────────────────

    public static function detect_device_type(string $user_agent = ''): string {
        if (!$user_agent) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        $mobile_patterns = ['iPhone', 'iPad', 'Android', 'Mobile', 'webOS', 'BlackBerry', 'IEMobile'];
        foreach ($mobile_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                // iPad con desktop mode: seguir contando como mobile
                return 'mobile';
            }
        }
        return 'desktop';
    }

    // ── Consultas de dispositivos ─────────────────────────────────────────────

    public static function get_device_by_user_and_type(int $user_id, string $device_type): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . "
             WHERE user_id = %d AND device_type = %s AND is_active = 1
             ORDER BY registered_date DESC LIMIT 1",
            $user_id, $device_type
        ));
    }

    public static function get_all_user_devices(int $user_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . "
             WHERE user_id = %d AND is_active = 1
             ORDER BY device_type ASC",
            $user_id
        ));
        $result = ['mobile' => null, 'desktop' => null];
        foreach ($rows as $row) {
            $result[$row->device_type] = $row;
        }
        return $result;
    }

    public static function user_has_device(int $user_id): bool {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table_name() . " WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
        return (int) $count > 0;
    }

    public static function user_has_device_for_type(int $user_id, string $device_type): bool {
        return self::get_device_by_user_and_type($user_id, $device_type) !== null;
    }

    public static function get_device_by_id(int $device_id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE id = %d",
            $device_id
        ));
    }

    public static function get_device_by_credential_id(string $credential_id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE credential_id = %s AND is_active = 1",
            $credential_id
        ));
    }

    public static function save_device(int $user_id, string $device_type, string $credential_id, string $public_key, string $device_name = ''): bool {
        global $wpdb;
        $result = $wpdb->insert(
            self::table_name(),
            [
                'user_id'       => $user_id,
                'device_type'   => $device_type,
                'credential_id' => $credential_id,
                'public_key'    => $public_key,
                'device_name'   => sanitize_text_field($device_name),
                'sign_count'    => 0,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );
        return $result !== false;
    }

    public static function update_sign_count(int $device_id, int $sign_count): void {
        global $wpdb;
        $wpdb->update(
            self::table_name(),
            ['sign_count' => $sign_count, 'last_access' => current_time('mysql')],
            ['id' => $device_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    /**
     * Revoca un dispositivo específico por su ID.
     * $user_initiated = true cuando lo hace el propio usuario (cuenta reemplazo).
     */
    public static function revoke_device_by_id(int $device_id, bool $user_initiated = false): bool {
        global $wpdb;
        return $wpdb->update(
            self::table_name(),
            [
                'is_active'       => 0,
                'deleted_date'    => current_time('mysql'),
                'deleted_by_user' => $user_initiated ? 1 : 0,
            ],
            ['id' => $device_id],
            ['%d', '%s', '%d'],
            ['%d']
        ) !== false;
    }

    /** Admin: revoca todos los dispositivos de un usuario */
    public static function admin_revoke_all_devices(int $user_id): bool {
        global $wpdb;
        return $wpdb->update(
            self::table_name(),
            ['is_active' => 0, 'deleted_date' => current_time('mysql'), 'deleted_by_user' => 0],
            ['user_id'   => $user_id],
            ['%d', '%s', '%d'],
            ['%d']
        ) !== false;
    }

    // ── Tracking de reemplazos ────────────────────────────────────────────────

    const MAX_REPLACEMENTS_PER_YEAR = 2;

    public static function get_replacement_count(int $user_id, string $device_type, int $year): int {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT count FROM " . self::replacements_table() . "
             WHERE user_id = %d AND device_type = %s AND year = %d",
            $user_id, $device_type, $year
        ));
        return (int) $count;
    }

    public static function increment_replacement_count(int $user_id, string $device_type, int $year): void {
        global $wpdb;
        $table = self::replacements_table();
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, count FROM {$table} WHERE user_id = %d AND device_type = %s AND year = %d",
            $user_id, $device_type, $year
        ));

        if ($existing) {
            $wpdb->update($table, ['count' => $existing->count + 1], ['id' => $existing->id], ['%d'], ['%d']);
        } else {
            $wpdb->insert($table, [
                'user_id'     => $user_id,
                'device_type' => $device_type,
                'year'        => $year,
                'count'       => 1,
            ], ['%d', '%s', '%d', '%d']);
        }
    }

    public static function user_is_blocked_for_slot(int $user_id, string $device_type): bool {
        $year  = (int) date('Y');
        $count = self::get_replacement_count($user_id, $device_type, $year);
        return $count >= self::MAX_REPLACEMENTS_PER_YEAR;
    }

    // ── Panel de admin ────────────────────────────────────────────────────────

    public static function get_all_devices(int $limit = 100, int $offset = 0): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, u.user_email, u.display_name
             FROM " . self::table_name() . " d
             LEFT JOIN {$wpdb->users} u ON u.ID = d.user_id
             WHERE d.is_active = 1
             ORDER BY d.registered_date DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        )) ?: [];
    }

    public static function count_active_devices(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table_name() . " WHERE is_active = 1");
    }

    // ── Auditoría de accesos ──────────────────────────────────────────────────

    public static function log_audit(string $action, int $user_id = 0, array $details = []): void {
        global $wpdb;

        $user_email = '';
        if ($user_id) {
            $user = get_userdata($user_id);
            $user_email = $user ? $user->user_email : '';
        }

        $device_type = $details['device_type'] ?? null;
        unset($details['device_type']);

        $wpdb->insert(
            self::audit_table(),
            [
                'user_id'     => $user_id,
                'user_email'  => $user_email,
                'ip'          => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                'action'      => $action,
                'device_type' => $device_type,
                'details'     => $details ? wp_json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Limpiar registros antiguos: mantener máximo 10,000
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::audit_table());
        if ($count > 10000) {
            $wpdb->query("DELETE FROM " . self::audit_table() . " ORDER BY id ASC LIMIT " . ($count - 10000));
        }
    }

    public static function get_audit_log(int $limit = 200, int $offset = 0): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::audit_table() . " ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit, $offset
        )) ?: [];
    }

    public static function count_audit_log(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::audit_table());
    }

    public static function clear_audit_log(): void {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::audit_table());
    }
}
