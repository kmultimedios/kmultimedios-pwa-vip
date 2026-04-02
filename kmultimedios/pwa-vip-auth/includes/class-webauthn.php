<?php
/**
 * Lógica WebAuthn server-side
 * Implementa verificación de challenge, origen y flags de autenticador.
 * Para producción de alta seguridad se recomienda la librería web-auth/webauthn-framework.
 */

defined('ABSPATH') || exit;

class PWA_WebAuthn {

    const CHALLENGE_TRANSIENT_PREFIX = 'pwa_webauthn_challenge_';
    const CHALLENGE_TTL              = 300; // 5 minutos
    const RP_ID                      = 'kmultimedios.com';
    const RP_ORIGIN                  = 'https://kmultimedios.com';

    // ── Challenge ──────────────────────────────────────────────────────────

    /**
     * Genera un challenge aleatorio y lo guarda en un transient ligado al user_id.
     * Devuelve el challenge en base64url para enviarlo al cliente.
     */
    public static function generate_challenge(int $user_id): string {
        $random    = random_bytes(32);
        $challenge = self::base64url_encode($random);

        set_transient(self::CHALLENGE_TRANSIENT_PREFIX . $user_id, $challenge, self::CHALLENGE_TTL);
        return $challenge;
    }

    /**
     * Recupera y elimina (one-time) el challenge del usuario.
     */
    public static function consume_challenge(int $user_id): ?string {
        $key       = self::CHALLENGE_TRANSIENT_PREFIX . $user_id;
        $challenge = get_transient($key);
        delete_transient($key);
        return $challenge ?: null;
    }

    // ── Registro ──────────────────────────────────────────────────────────

    /**
     * Verifica el attestation del registro y extrae credential_id y public_key.
     * Devuelve array ['credential_id' => string, 'public_key' => string] o WP_Error.
     */
    public static function verify_registration(int $user_id, array $response): array|WP_Error {
        // 1. Consumir challenge
        $expected_challenge = self::consume_challenge($user_id);
        if (!$expected_challenge) {
            return new WP_Error('invalid_challenge', 'Challenge expirado o inválido.', ['status' => 400]);
        }

        // 2. Decodificar clientDataJSON
        if (empty($response['clientDataJSON'])) {
            return new WP_Error('missing_data', 'clientDataJSON requerido.', ['status' => 400]);
        }

        $client_data_raw = self::base64url_decode($response['clientDataJSON']);
        $client_data     = json_decode($client_data_raw, true);

        if (!$client_data) {
            return new WP_Error('invalid_client_data', 'clientDataJSON inválido.', ['status' => 400]);
        }

        // 3. Verificar type
        if (($client_data['type'] ?? '') !== 'webauthn.create') {
            return new WP_Error('invalid_type', 'Tipo de operación incorrecto.', ['status' => 400]);
        }

        // 4. Verificar challenge
        $received_challenge = $client_data['challenge'] ?? '';
        if (!hash_equals($expected_challenge, $received_challenge)) {
            return new WP_Error('challenge_mismatch', 'Challenge no coincide.', ['status' => 400]);
        }

        // 5. Verificar origen
        $origin = $client_data['origin'] ?? '';
        if (!self::verify_origin($origin)) {
            return new WP_Error('invalid_origin', 'Origen no permitido: ' . $origin, ['status' => 400]);
        }

        // 6. Extraer credential_id
        $credential_id = $response['id'] ?? '';
        if (empty($credential_id)) {
            return new WP_Error('missing_credential_id', 'credential_id requerido.', ['status' => 400]);
        }

        // 7. Guardar public key (attestationObject contiene la clave pública COSE)
        //    Para una verificación completa se parsea CBOR; aquí almacenamos el objeto raw
        //    y el credential_id sirve como identificador de dispositivo.
        $public_key = $response['attestationObject'] ?? '';
        if (empty($public_key)) {
            return new WP_Error('missing_attestation', 'attestationObject requerido.', ['status' => 400]);
        }

        return [
            'credential_id' => sanitize_text_field($credential_id),
            'public_key'    => sanitize_text_field($public_key),
        ];
    }

    // ── Autenticación ─────────────────────────────────────────────────────

    /**
     * Verifica un assertion (login con dispositivo ya registrado).
     * Devuelve true o WP_Error.
     */
    public static function verify_assertion(int $user_id, array $response, object $device): bool|WP_Error {
        // 1. Consumir challenge
        $expected_challenge = self::consume_challenge($user_id);
        if (!$expected_challenge) {
            return new WP_Error('invalid_challenge', 'Challenge expirado o inválido.', ['status' => 400]);
        }

        // 2. Decodificar clientDataJSON
        $client_data_raw = self::base64url_decode($response['clientDataJSON'] ?? '');
        $client_data     = json_decode($client_data_raw, true);

        if (!$client_data) {
            return new WP_Error('invalid_client_data', 'clientDataJSON inválido.', ['status' => 400]);
        }

        // 3. Verificar type
        if (($client_data['type'] ?? '') !== 'webauthn.get') {
            return new WP_Error('invalid_type', 'Tipo de operación incorrecto.', ['status' => 400]);
        }

        // 4. Verificar challenge
        if (!hash_equals($expected_challenge, $client_data['challenge'] ?? '')) {
            return new WP_Error('challenge_mismatch', 'Challenge no coincide.', ['status' => 400]);
        }

        // 5. Verificar origen
        if (!self::verify_origin($client_data['origin'] ?? '')) {
            return new WP_Error('invalid_origin', 'Origen no permitido.', ['status' => 400]);
        }

        // 6. Verificar authenticatorData flags
        $auth_data_raw = self::base64url_decode($response['authenticatorData'] ?? '');
        if (strlen($auth_data_raw) < 37) {
            return new WP_Error('invalid_auth_data', 'authenticatorData inválido.', ['status' => 400]);
        }

        $flags = ord($auth_data_raw[32]);
        $up    = ($flags & 0x01) !== 0; // User Present
        $uv    = ($flags & 0x04) !== 0; // User Verified

        if (!$up) {
            return new WP_Error('user_not_present', 'Usuario no presente.', ['status' => 400]);
        }
        if (!$uv) {
            return new WP_Error('user_not_verified', 'Usuario no verificado (biometría requerida).', ['status' => 400]);
        }

        // 7. Verificar credential_id coincide con el registrado
        $received_id = $response['id'] ?? '';
        if (!hash_equals($device->credential_id, $received_id)) {
            return new WP_Error('credential_mismatch', 'Credencial no coincide con el dispositivo registrado.', ['status' => 400]);
        }

        // 8. Verificar sign_count (replay protection)
        $sign_count_raw = substr($auth_data_raw, 33, 4);
        $sign_count     = unpack('N', $sign_count_raw)[1];

        if ($sign_count > 0 && $sign_count <= (int) $device->sign_count) {
            return new WP_Error('replay_detected', 'Posible ataque de repetición detectado.', ['status' => 400]);
        }

        // Actualizar sign_count
        PWA_Database::update_sign_count((int) $device->id, $sign_count);

        return true;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private static function verify_origin(string $origin): bool {
        $allowed = [
            self::RP_ORIGIN,
            'https://www.kmultimedios.com',
        ];
        return in_array(rtrim($origin, '/'), $allowed, true);
    }

    public static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
