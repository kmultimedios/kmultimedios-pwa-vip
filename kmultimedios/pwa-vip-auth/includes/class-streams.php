<?php
/**
 * Configuración de zonas y cámaras
 * Fuente única de verdad – reemplaza el array $border_zones del template PHP
 */

defined('ABSPATH') || exit;

class PWA_Streams {

    const PROXY_BASE   = 'https://proxy.kmultimedios.com';
    const AUDIO_URL    = 'https://streamers.kmultiradio.com/8002/stream';
    const SECRET_KEY   = 'videoplus_secret_2025';
    const TOKEN_EXPIRY = 300; // 5 minutos

    // Map hls_key → camera_id (debe coincidir con cameras.json del proxy)
    const CAMERA_IDS = [
        'proxy_e285327477b6d4ff' => '69532ff72565c',
        'proxy_148a60d3da5b4f5f' => '69533a3e615d7',
        'proxy_dd020195b1320f75' => '69533a6cb0349',
        'proxy_eee9c86c2c3c67b8' => '69533ace93550',
        'proxy_4065bd7c5d3a9e9e' => '69533af07a5aa',
    ];

    /**
     * Configuración completa de zonas y cámaras.
     * Edita aquí para agregar/quitar cámaras.
     */
    public static function get_zones(): array {
        return [
            'douglas_ap' => [
                'zone_name'     => 'Douglas - Agua Prieta',
                'zone_code'     => 'DGL-AP',
                'coordinates'   => ['lat' => 31.3448, 'lng' => -109.5456],
                'status'        => 'EN LÍNEA',
                'traffic_level' => 'FLUIDO',
                'sections' => [
                    [
                        'title'     => 'Douglas → Agua Prieta',
                        'direction' => 'SOUTHBOUND',
                        'icon'      => 'fa-arrow-down',
                        'cameras'   => [
                            [
                                'hls_key'   => 'proxy_e285327477b6d4ff',
                                'name'      => 'Border Mart',
                                'code'      => 'DGL-BM-01',
                                'location'  => 'Douglas AZ',
                                'type'      => 'COMERCIAL',
                                'priority'  => 'high',
                                'audio_url' => self::AUDIO_URL,
                            ],
                            [
                                'hls_key'   => 'proxy_148a60d3da5b4f5f',
                                'name'      => 'Cruce Panamericana',
                                'code'      => 'DGL-PJ-02',
                                'location'  => 'Douglas AZ',
                                'type'      => 'TRÁFICO',
                                'priority'  => 'medium',
                                'audio_url' => self::AUDIO_URL,
                            ],
                        ],
                    ],
                    [
                        'title'     => 'Agua Prieta → Douglas',
                        'direction' => 'NORTHBOUND',
                        'icon'      => 'fa-arrow-up',
                        'cameras'   => [
                            [
                                'hls_key'   => 'proxy_dd020195b1320f75',
                                'name'      => 'Cruce Peatonal',
                                'code'      => 'AP-PE-01',
                                'location'  => 'Agua Prieta SON',
                                'type'      => 'PEATONES',
                                'priority'  => 'high',
                                'audio_url' => self::AUDIO_URL,
                            ],
                            [
                                'hls_key'   => 'proxy_eee9c86c2c3c67b8',
                                'name'      => 'Carriles Oeste',
                                'code'      => 'AP-VW-02',
                                'location'  => 'Agua Prieta SON',
                                'type'      => 'VEHÍCULOS',
                                'priority'  => 'high',
                                'audio_url' => self::AUDIO_URL,
                            ],
                            [
                                'hls_key'   => 'proxy_4065bd7c5d3a9e9e',
                                'name'      => 'Carriles Este',
                                'code'      => 'AP-VE-03',
                                'location'  => 'Agua Prieta SON',
                                'type'      => 'VEHÍCULOS',
                                'priority'  => 'medium',
                                'audio_url' => self::AUDIO_URL,
                            ],
                        ],
                    ],
                ],
            ],

            'nogales' => [
                'zone_name'     => 'Nogales Sonora - Arizona',
                'zone_code'     => 'NOG-MRP',
                'coordinates'   => ['lat' => 31.3404, 'lng' => -110.9398],
                'status'        => 'EN LÍNEA',
                'traffic_level' => 'MODERADO',
                'sections' => [
                    [
                        'title'     => 'Garita Mariposa',
                        'direction' => 'NORTHBOUND',
                        'icon'      => 'fa-arrow-up',
                        'cameras'   => [
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/mariposa/general/index.m3u8',
                                'name'     => 'Mariposa Vista General',
                                'code'     => 'MRP-GV-01',
                                'location' => 'Nogales AZ',
                                'type'     => 'PANORÁMICA',
                                'priority' => 'high',
                            ],
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/pla-sur/index.m3u8',
                                'name'     => 'Mariposa Plataforma Sur',
                                'code'     => 'MRP-SP-02',
                                'location' => 'Nogales AZ',
                                'type'     => 'PLATAFORMA',
                                'priority' => 'medium',
                            ],
                        ],
                    ],
                    [
                        'title'     => 'Garita DeConcini',
                        'direction' => 'NORTHBOUND',
                        'icon'      => 'fa-arrow-up',
                        'cameras'   => [
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/norte/index.m3u8',
                                'name'     => 'DeConcini Acceso Norte',
                                'code'     => 'DCC-NA-01',
                                'location' => 'Nogales AZ',
                                'type'     => 'ACCESO',
                                'priority' => 'high',
                            ],
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/sur/index.m3u8',
                                'name'     => 'DeConcini Acceso Sur',
                                'code'     => 'DCC-SA-02',
                                'location' => 'Nogales AZ',
                                'type'     => 'ACCESO',
                                'priority' => 'high',
                            ],
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/ban-nor/index.m3u8',
                                'name'     => 'DeConcini Carril Sur 2',
                                'code'     => 'DCC-SL-03',
                                'location' => 'Nogales AZ',
                                'type'     => 'CARRIL',
                                'priority' => 'medium',
                            ],
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/hot-nor/index.m3u8',
                                'name'     => 'SENTRI Acceso Norte',
                                'code'     => 'SNT-NA-01',
                                'location' => 'Nogales AZ',
                                'type'     => 'SENTRI',
                                'priority' => 'medium',
                            ],
                            [
                                'hls_url'  => 'https://cruce.heroicanogales.gob.mx/deconcini/hot-sur/index.m3u8',
                                'name'     => 'SENTRI Acceso Sur',
                                'code'     => 'SNT-SA-02',
                                'location' => 'Nogales AZ',
                                'type'     => 'SENTRI',
                                'priority' => 'medium',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Construye la URL HLS de un stream proxy dado su key.
     * Si se pasa user_id, genera una URL con token firmado.
     */
    public static function build_proxy_url(string $hls_key, int $user_id = 0): string {
        $base = self::PROXY_BASE . '/hls/protected/' . $hls_key . '.m3u8';
        if ($user_id <= 0 || empty(self::CAMERA_IDS[$hls_key])) {
            return $base;
        }
        $camera_id = self::CAMERA_IDS[$hls_key];
        $expires   = time() + self::TOKEN_EXPIRY;
        $secret    = hash('sha256', self::SECRET_KEY);
        $payload   = $camera_id . '|' . $expires . '|' . $user_id;
        $token     = hash_hmac('sha256', $payload, $secret);
        return $base . '?token=' . urlencode($token) . '&expires=' . $expires . '&user_id=' . $user_id;
    }

    /**
     * Prepara las zonas para la API: sustituye hls_key por hls_url completa.
     * No expone los keys originales al cliente.
     */
    public static function get_zones_for_api(int $user_id = 0): array {
        $zones = self::get_zones();
        foreach ($zones as &$zone) {
            foreach ($zone['sections'] as &$section) {
                foreach ($section['cameras'] as &$cam) {
                    if (!empty($cam['hls_key'])) {
                        $cam['hls_url'] = self::build_proxy_url($cam['hls_key'], $user_id);
                        unset($cam['hls_key']); // nunca exponer la key raw
                    }
                }
            }
        }
        return $zones;
    }

    /**
     * Datos de watermark del usuario actual.
     */
    public static function get_watermark(int $user_id): array {
        $user = get_userdata($user_id);

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
           ?? $_SERVER['HTTP_X_FORWARDED_FOR']
           ?? $_SERVER['REMOTE_ADDR']
           ?? '0.0.0.0';

        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $level = '';
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $lvl = pmpro_getMembershipLevelForUser($user_id);
            if ($lvl) $level = strtoupper($lvl->name);
        }

        return [
            'email'       => $user->user_email ?? 'desconocido',
            'display_name'=> $user->display_name ?? '',
            'ip'          => $ip,
            'uid'         => $user_id,
            'level'       => $level,
            'server_time' => wp_date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Total de cámaras en todas las zonas.
     */
    public static function count_cameras(): int {
        $n = 0;
        foreach (self::get_zones() as $zone) {
            foreach ($zone['sections'] as $section) {
                $n += count($section['cameras']);
            }
        }
        return $n;
    }
}
