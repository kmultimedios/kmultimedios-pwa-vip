<?php
/**
 * Detector inteligente de dispositivos con algoritmos mejorados
 * 
 * @since 5.0.0
 */
class KMUL_Detector {
    
    private $database;
    
    // Patrones de riesgo configurables
    const RISK_LEVELS = [
        'low' => 1,
        'medium' => 2, 
        'high' => 3,
        'critical' => 4
    ];
    
    // Tipos de dispositivos reconocidos
    const DEVICE_TYPES = ['PC', 'Mobile', 'Tablet', 'TV', 'Console'];
    
    public function __construct(KMUL_Database $database) {
        $this->database = $database;
    }
    
    /**
     * Detectar información del dispositivo desde user agent
     */
    public function detect_device_info($user_agent, $ip_address) {
        if (!$this->validate_user_agent($user_agent)) {
            return false;
        }
        
        return [
            'operating_system' => $this->detect_operating_system($user_agent),
            'browser' => $this->detect_browser($user_agent),
            'device_type' => $this->detect_device_type($user_agent),
            'ip_address' => $this->sanitize_ip($ip_address),
            'user_agent_hash' => hash('sha256', $user_agent) // Para privacidad
        ];
    }
    
    /**
     * Análisis de riesgo mejorado para usuarios
     */
    public function analyze_user_risk($user_statistics) {
        $risk_score = 0;
        $risk_factors = [];
        $risk_level = 'low';
        
        // Factor 1: Múltiples tipos de dispositivos
        if ($user_statistics->unique_devices > 1) {
            $device_risk = $this->calculate_device_type_risk($user_statistics->user_id);
            $risk_score += $device_risk['score'];
            $risk_factors = array_merge($risk_factors, $device_risk['factors']);
        }
        
        // Factor 2: Múltiples IPs
        if ($user_statistics->unique_ips > 2) {
            $ip_risk = $this->calculate_ip_risk($user_statistics->unique_ips);
            $risk_score += $ip_risk['score'];
            $risk_factors[] = $ip_risk['factor'];
        }
        
        // Factor 3: Patrones de acceso anómalos
        $pattern_risk = $this->analyze_access_patterns($user_statistics);
        $risk_score += $pattern_risk['score'];
        if (!empty($pattern_risk['factor'])) {
            $risk_factors[] = $pattern_risk['factor'];
        }
        
        // Factor 4: Frecuencia de cambios de dispositivo
        $frequency_risk = $this->analyze_device_switching($user_statistics->user_id);
        $risk_score += $frequency_risk['score'];
        if (!empty($frequency_risk['factor'])) {
            $risk_factors[] = $frequency_risk['factor'];
        }
        
        // Determinar nivel de riesgo
        if ($risk_score >= 8) {
            $risk_level = 'critical';
        } elseif ($risk_score >= 6) {
            $risk_level = 'high';
        } elseif ($risk_score >= 3) {
            $risk_level = 'medium';
        }
        
        return [
            'risk_level' => $risk_level,
            'risk_score' => $risk_score,
            'risk_factors' => $risk_factors,
            'recommendation' => $this->get_risk_recommendation($risk_level, $risk_factors)
        ];
    }
    
    /**
     * Calcular riesgo por tipos de dispositivos
     */
    private function calculate_device_type_risk($user_id) {
        $devices = $this->database->get_user_devices($user_id);
        $device_types = array_unique(array_column($devices, 'device_type'));
        $os_types = array_unique(array_column($devices, 'operating_system'));
        
        $score = 0;
        $factors = [];
        
        // PC + Mobile = Alto riesgo
        if (in_array('PC', $device_types) && (in_array('Mobile', $device_types) || in_array('Tablet', $device_types))) {
            $score += 4;
            $factors[] = 'Acceso desde PC y dispositivo móvil';
        }
        
        // Múltiples sistemas operativos móviles
        $mobile_os = array_intersect($os_types, ['Android', 'iPhone', 'iPad']);
        if (count($mobile_os) > 1) {
            $score += 3;
            $factors[] = 'Múltiples sistemas operativos móviles detectados';
        }
        
        // Más de 3 tipos diferentes de dispositivos
        if (count($device_types) > 3) {
            $score += 2;
            $factors[] = 'Demasiados tipos de dispositivos diferentes';
        }
        
        return ['score' => $score, 'factors' => $factors];
    }
    
    /**
     * Calcular riesgo por múltiples IPs
     */
    private function calculate_ip_risk($unique_ips) {
        $score = 0;
        $factor = '';
        
        if ($unique_ips > 10) {
            $score = 4;
            $factor = 'Acceso desde más de 10 IPs diferentes';
        } elseif ($unique_ips > 5) {
            $score = 3;
            $factor = 'Acceso desde más de 5 IPs diferentes';
        } elseif ($unique_ips > 3) {
            $score = 2;
            $factor = 'Múltiples IPs de acceso';
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Analizar patrones de acceso anómalos
     */
    private function analyze_access_patterns($user_stats) {
        $score = 0;
        $factor = '';
        
        // Ratio alto de dispositivos vs accesos (cambios frecuentes)
        if ($user_stats->total_accesses > 0) {
            $device_change_ratio = $user_stats->total_records / $user_stats->total_accesses;
            
            if ($device_change_ratio > 0.5) {
                $score = 3;
                $factor = 'Cambios de dispositivo muy frecuentes';
            } elseif ($device_change_ratio > 0.3) {
                $score = 2;
                $factor = 'Cambios de dispositivo moderados';
            }
        }
        
        // Acceso desde demasiadas combinaciones únicas
        if ($user_stats->device_combinations > 5) {
            $score += 2;
            $factor .= $factor ? ' y múltiples combinaciones de dispositivo' : 'Múltiples combinaciones de dispositivo';
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Analizar frecuencia de cambios de dispositivo
     */
    private function analyze_device_switching($user_id) {
        global $wpdb;
        
        $table_logs = $this->database->get_table_names()['logs'];
        
        // Obtener accesos de los últimos 30 días ordenados por fecha
        $recent_accesses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT device_type, operating_system, login_date, ip_address
                 FROM {$table_logs} 
                 WHERE user_id = %d AND login_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ORDER BY login_date ASC",
                $user_id
            )
        );
        
        if (count($recent_accesses) < 3) {
            return ['score' => 0, 'factor' => ''];
        }
        
        $switches = 0;
        $last_device = '';
        
        foreach ($recent_accesses as $access) {
            $current_device = $access->device_type . '_' . $access->operating_system;
            if ($last_device && $last_device !== $current_device) {
                $switches++;
            }
            $last_device = $current_device;
        }
        
        $score = 0;
        $factor = '';
        
        if ($switches > 10) {
            $score = 3;
            $factor = 'Cambios de dispositivo excesivamente frecuentes';
        } elseif ($switches > 5) {
            $score = 2;
            $factor = 'Cambios de dispositivo frecuentes';
        }
        
        return ['score' => $score, 'factor' => $factor];
    }
    
    /**
     * Detectar sistema operativo mejorado
     */
    private function detect_operating_system($user_agent) {
        $user_agent = strtolower($user_agent);
        
        // Orden específico importante para evitar falsos positivos
        if (strpos($user_agent, 'windows nt 10.0') !== false) return 'Windows 10';
        if (strpos($user_agent, 'windows nt 6.3') !== false) return 'Windows 8.1';
        if (strpos($user_agent, 'windows nt 6.1') !== false) return 'Windows 7';
        if (strpos($user_agent, 'windows nt') !== false) return 'Windows';
        
        if (strpos($user_agent, 'iphone') !== false) return 'iPhone';
        if (strpos($user_agent, 'ipad') !== false) return 'iPad';
        if (strpos($user_agent, 'mac os x') !== false) return 'macOS';
        if (strpos($user_agent, 'macintosh') !== false) return 'macOS';
        
        if (strpos($user_agent, 'android') !== false) return 'Android';
        
        if (strpos($user_agent, 'ubuntu') !== false) return 'Ubuntu';
        if (strpos($user_agent, 'linux') !== false) return 'Linux';
        
        if (strpos($user_agent, 'cros') !== false) return 'Chrome OS';
        
        return 'Desconocido';
    }
    
    /**
     * Detectar navegador mejorado
     */
    private function detect_browser($user_agent) {
        $user_agent = strtolower($user_agent);
        
        // Orden específico para evitar conflictos
        if (strpos($user_agent, 'edg/') !== false || strpos($user_agent, 'edge/') !== false) return 'Edge';
        if (strpos($user_agent, 'opr/') !== false || strpos($user_agent, 'opera/') !== false) return 'Opera';
        if (strpos($user_agent, 'chrome/') !== false) return 'Chrome';
        if (strpos($user_agent, 'firefox/') !== false) return 'Firefox';
        if (strpos($user_agent, 'safari/') !== false && strpos($user_agent, 'chrome/') === false) return 'Safari';
        if (strpos($user_agent, 'trident/') !== false || strpos($user_agent, 'msie') !== false) return 'Internet Explorer';
        
        return 'Desconocido';
    }
    
    /**
     * Detectar tipo de dispositivo mejorado
     */
    private function detect_device_type($user_agent) {
        $user_agent = strtolower($user_agent);
        
        // Smart TV y consolas primero
        if (preg_match('/smart-?tv|googletv|appletv|roku|chromecast|netflix|hulu/i', $user_agent)) return 'TV';
        if (preg_match('/playstation|xbox|nintendo|wii/i', $user_agent)) return 'Console';
        
        // Tablets antes que móviles
        if (strpos($user_agent, 'ipad') !== false) return 'Tablet';
        if (strpos($user_agent, 'tablet') !== false) return 'Tablet';
        if (preg_match('/kindle|nook|kobo/i', $user_agent)) return 'Tablet';
        
        // Dispositivos móviles
        if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone|opera mini/i', $user_agent)) {
            return 'Mobile';
        }
        
        // Por defecto PC
        return 'PC';
    }
    
    /**
     * Obtener recomendación basada en riesgo
     */
    private function get_risk_recommendation($risk_level, $risk_factors) {
        switch ($risk_level) {
            case 'critical':
                return [
                    'action' => 'block',
                    'message' => 'Bloqueo inmediato recomendado',
                    'details' => 'Múltiples indicadores de compartición de cuenta detectados'
                ];
                
            case 'high':
                return [
                    'action' => 'warn_strict',
                    'message' => 'Advertencia severa requerida',
                    'details' => 'Patrón de uso altamente sospechoso'
                ];
                
            case 'medium':
                return [
                    'action' => 'warn',
                    'message' => 'Enviar advertencia estándar',
                    'details' => 'Uso potencialmente irregular detectado'
                ];
                
            default:
                return [
                    'action' => 'monitor',
                    'message' => 'Continuar monitoreando',
                    'details' => 'Uso dentro de parámetros normales'
                ];
        }
    }
    
    /**
     * Validar user agent
     */
    private function validate_user_agent($user_agent) {
        if (empty($user_agent)) return false;
        if (strlen($user_agent) > 2000) return false; // Límite de seguridad
        if (!mb_check_encoding($user_agent, 'UTF-8')) return false;
        
        return true;
    }
    
    /**
     * Sanitizar y validar IP
     */
    private function sanitize_ip($ip) {
        // Obtener IP real considerando proxies y CDNs
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',           // Nginx proxy
            'HTTP_X_FORWARDED_FOR',     // Proxy estándar
            'HTTP_CLIENT_IP',           // ISP proxy
            'REMOTE_ADDR'               // IP directa
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Si hay múltiples IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar IP pública válida
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback a IP directa
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clasificar usuarios por nivel de riesgo
     */
    public function classify_users_by_risk($users_statistics) {
        $classification = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => []
        ];
        
        foreach ($users_statistics as $user_stat) {
            $risk_analysis = $this->analyze_user_risk($user_stat);
            $classification[$risk_analysis['risk_level']][] = [
                'user_data' => $user_stat,
                'risk_analysis' => $risk_analysis
            ];
        }
        
        return $classification;
    }
    
    /**
     * Detectar patrones de bot o automatización
     */
    public function detect_bot_patterns($user_agent, $access_pattern = []) {
        $user_agent = strtolower($user_agent);
        $bot_indicators = 0;
        
        // Patrones comunes de bots
        $bot_signatures = [
            'bot', 'crawler', 'spider', 'scraper', 'parser',
            'curl', 'wget', 'http', 'request', 'python',
            'automated', 'script', 'tool'
        ];
        
        foreach ($bot_signatures as $signature) {
            if (strpos($user_agent, $signature) !== false) {
                $bot_indicators++;
            }
        }
        
        // User agent muy corto o muy simple
        if (strlen($user_agent) < 20 || !strpos($user_agent, '/')) {
            $bot_indicators++;
        }
        
        // Accesos muy frecuentes en poco tiempo (si se proporcionan)
        if (!empty($access_pattern['requests_per_minute']) && $access_pattern['requests_per_minute'] > 10) {
            $bot_indicators++;
        }
        
        return $bot_indicators >= 2;
    }
    
    /**
     * Generar reporte de análisis completo
     */
    public function generate_analysis_report($user_id) {
        $user_devices = $this->database->get_user_devices($user_id);
        if (empty($user_devices)) {
            return null;
        }
        
        // Estadísticas básicas
        $stats = (object)[
            'user_id' => $user_id,
            'username' => $user_devices[0]['username'],
            'total_records' => count($user_devices),
            'unique_ips' => count(array_unique(array_column($user_devices, 'ip_address'))),
            'unique_devices' => count(array_unique(array_column($user_devices, 'device_type'))),
            'device_combinations' => count(array_unique(array_map(function($d) {
                return $d['operating_system'] . '-' . $d['device_type'];
            }, $user_devices))),
            'total_accesses' => array_sum(array_column($user_devices, 'access_count')),
            'last_access' => max(array_column($user_devices, 'last_seen')),
            'first_access' => min(array_column($user_devices, 'created_at'))
        ];
        
        $risk_analysis = $this->analyze_user_risk($stats);
        
        return [
            'user_stats' => $stats,
            'risk_analysis' => $risk_analysis,
            'devices' => $user_devices,
            'recommendations' => $this->get_detailed_recommendations($risk_analysis, $user_devices)
        ];
    }
    
    /**
     * Generar recomendaciones detalladas
     */
    private function get_detailed_recommendations($risk_analysis, $devices) {
        $recommendations = [];
        
        if ($risk_analysis['risk_level'] === 'critical') {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Bloquear cuenta inmediatamente',
                'reason' => 'Múltiples indicadores críticos detectados'
            ];
        }
        
        if ($risk_analysis['risk_level'] === 'high') {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Enviar advertencia severa',
                'reason' => 'Patrón de uso altamente sospechoso'
            ];
            
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Monitorear intensivamente',
                'reason' => 'Seguimiento cercano requerido'
            ];
        }
        
        if (count($devices) > 5) {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'Revisar dispositivos antiguos',
                'reason' => 'Considerar limpiar dispositivos no utilizados'
            ];
        }
        
        return $recommendations;
    }
}
?>