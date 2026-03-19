<?php
/**
 * Integración con Paid Memberships Pro
 */

defined('ABSPATH') || exit;

class PWA_PMP {

    /**
     * IDs de niveles de membresía VIP.
     * Ajustar según configuración de PMP en kmultimedios.com
     */
    const VIP_LEVELS = [4, 5, 8, 9, 10, 11];

    /**
     * Comprueba si un usuario tiene membresía VIP activa.
     */
    public static function is_vip(int $user_id): bool {
        if (!function_exists('pmpro_hasMembershipLevel')) {
            // PMP no instalado – en desarrollo permitir admins
            return current_user_can('administrator');
        }
        return (bool) pmpro_hasMembershipLevel(self::VIP_LEVELS, $user_id);
    }

    /**
     * Devuelve el nivel de membresía activo del usuario o null.
     */
    public static function get_level(int $user_id): ?object {
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            return null;
        }
        return pmpro_getMembershipLevelForUser($user_id) ?: null;
    }

    /**
     * Obtiene las páginas protegidas accesibles para el nivel del usuario.
     * Devuelve array de WP_Post o vacío.
     */
    public static function get_accessible_content(int $user_id): array {
        if (!self::is_vip($user_id)) {
            return [];
        }

        $level = self::get_level($user_id);
        if (!$level) {
            return [];
        }

        // Buscar posts/páginas que PMP protege para este nivel
        $args = [
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => [
                [
                    'key'     => '_pmpro_require_membership',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $posts = get_posts($args);

        // Filtrar solo los accesibles para este usuario
        return array_filter($posts, function ($post) use ($user_id) {
            return !function_exists('pmpro_has_membership_access') ||
                   pmpro_has_membership_access($post->ID, $user_id);
        });
    }

    /**
     * Nombre del nivel del usuario.
     */
    public static function get_level_name(int $user_id): string {
        $level = self::get_level($user_id);
        return $level ? esc_html($level->name) : 'VIP';
    }

    /**
     * Fecha de expiración de la membresía (null = sin expiración).
     */
    public static function get_expiry(int $user_id): ?string {
        $level = self::get_level($user_id);
        if (!$level || empty($level->enddate) || $level->enddate === '0000-00-00 00:00:00') {
            return null;
        }
        return $level->enddate;
    }
}
